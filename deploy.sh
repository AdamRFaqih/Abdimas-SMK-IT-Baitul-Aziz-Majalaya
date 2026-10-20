#!/usr/bin/env bash
# ==============================================================================
# SMK IT Baitul Aziz - Docker Deployment & Management Script
# ==============================================================================

set -e

# Color definitions
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Helper output functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Determine project root
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_DIR"

# Check Docker & Docker Compose installation
check_prerequisites() {
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed or not available in PATH."
        echo "Please install Docker from https://docs.docker.com/get-docker/ and make sure the Docker daemon is running."
        exit 1
    fi

    if ! docker compose version &> /dev/null; then
        log_error "'docker compose' command is not available."
        echo "Please ensure you have Docker Compose v2 installed."
        exit 1
    fi

    # Check if Docker daemon is running
    if ! docker info &> /dev/null; then
        log_error "Docker daemon is not running. Please start Docker first."
        exit 1
    fi
}

# Ensure .env exists and is prepared
setup_env() {
    if [ ! -f .env ]; then
        log_warn ".env file not found. Initializing from .env.docker.example..."
        cp .env.docker.example .env
        log_success "Created .env from .env.docker.example"
    fi

    # Ensure DB_HOST is set to 'db' for Docker deployment
    if grep -q "DB_HOST=127.0.0.1" .env; then
        log_info "Updating DB_HOST in .env from 127.0.0.1 to 'db' for Docker network..."
        # macOS and Linux compatible sed
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' 's/DB_HOST=127.0.0.1/DB_HOST=db/' .env
        else
            sed -i 's/DB_HOST=127.0.0.1/DB_HOST=db/' .env
        fi
    fi

    # Ensure APP_KEY exists before starting containers to prevent container permission issues
    if ! grep -q -E "^APP_KEY=base64:" .env; then
        log_info "Generating APP_KEY for .env..."
        local NEW_KEY=""
        if command -v openssl &> /dev/null; then
            NEW_KEY="base64:$(openssl rand -base64 32)"
        elif command -v php &> /dev/null; then
            NEW_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
        fi

        if [ -n "$NEW_KEY" ]; then
            if grep -q "^APP_KEY=" .env; then
                if [[ "$OSTYPE" == "darwin"* ]]; then
                    sed -i '' "s|^APP_KEY=.*|APP_KEY=${NEW_KEY}|" .env
                else
                    sed -i "s|^APP_KEY=.*|APP_KEY=${NEW_KEY}|" .env
                fi
            else
                echo "APP_KEY=${NEW_KEY}" >> .env
            fi
            log_success "APP_KEY created: ${NEW_KEY:0:15}..."
        fi
    fi

    # Grant read/write permissions to .env so both host and containers can access it
    chmod 666 .env 2>/dev/null || true
}

# Wait for DB service to be healthy
wait_for_db() {
    log_info "Waiting for MySQL database container to become ready..."
    local max_retries=30
    local retry_count=0
    until docker compose exec -T db mysqladmin ping -h localhost -u root -p"$(grep DB_ROOT_PASSWORD .env | cut -d '=' -f2 | tr -d '\"' || echo 'root_secret')" --silent &> /dev/null; do
        retry_count=$((retry_count + 1))
        if [ $retry_count -ge $max_retries ]; then
            log_error "MySQL database did not become ready within timeout."
            exit 1
        fi
        sleep 2
    done
    log_success "Database is ready!"
}

# Main deployment routine
do_deploy() {
    echo -e "${CYAN}====================================================${NC}"
    echo -e "${CYAN}   SMK IT Baitul Aziz - Docker Deployment Starting   ${NC}"
    echo -e "${CYAN}====================================================${NC}"

    check_prerequisites
    setup_env

    log_info "Building and launching Docker containers..."
    docker compose up -d --build

    wait_for_db

    log_info "Running database migrations..."
    docker compose exec -T app php artisan migrate --force

    # Check if user requested database seed
    if [ "$1" = "--seed" ] || [ "$2" = "--seed" ]; then
        log_info "Running database seeders..."
        docker compose exec -T app php artisan db:seed --force
    fi

    log_info "Creating storage symlink..."
    docker compose exec -T app php artisan storage:link --force 2>/dev/null || true

    log_info "Optimizing caches (routes, views, config)..."
    docker compose exec -T app php artisan optimize

    # Extract configured port from .env or default to 8000
    APP_PORT=$(grep -E "^APP_PORT=" .env | cut -d '=' -f2 | tr -d ' ' || echo "8000")
    APP_PORT=${APP_PORT:-8000}

    echo ""
    echo -e "${GREEN}====================================================${NC}"
    echo -e "${GREEN}      Deployment Completed Successfully! 🎉         ${NC}"
    echo -e "${GREEN}====================================================${NC}"
    echo -e "Application URL : ${CYAN}http://localhost:${APP_PORT}${NC}"
    echo -e "Database Port   : ${CYAN}localhost:${DB_EXTERNAL_PORT:-3306}${NC}"
    echo ""
    echo -e "Useful management commands:"
    echo -e "  - Seed database     : ${YELLOW}./deploy.sh seed${NC}"
    echo -e "  - View live logs    : ${YELLOW}./deploy.sh logs${NC}"
    echo -e "  - Access app shell  : ${YELLOW}./deploy.sh shell${NC}"
    echo -e "  - Backup database   : ${YELLOW}./deploy.sh backup${NC}"
    echo -e "  - Stop application  : ${YELLOW}./deploy.sh down${NC}"
    echo ""
}

# Command dispatch
case "$1" in
    ""|"deploy")
        do_deploy "$@"
        ;;
    "--seed")
        do_deploy "--seed"
        ;;
    "seed")
        check_prerequisites
        log_info "Running database seeders..."
        docker compose exec app php artisan db:seed --force
        log_success "Seeding completed."
        ;;
    "migrate")
        check_prerequisites
        log_info "Running database migrations..."
        docker compose exec app php artisan migrate --force
        log_success "Migrations completed."
        ;;
    "down"|"stop")
        check_prerequisites
        log_info "Stopping containers..."
        docker compose down
        log_success "Containers stopped."
        ;;
    "restart")
        check_prerequisites
        log_info "Restarting containers..."
        docker compose restart
        log_success "Containers restarted."
        ;;
    "logs")
        check_prerequisites
        TARGET=${2:-app}
        log_info "Tailing logs for '$TARGET' (Press Ctrl+C to exit)..."
        docker compose logs -f "$TARGET"
        ;;
    "shell"|"bash")
        check_prerequisites
        TARGET=${2:-app}
        log_info "Opening interactive shell into container '$TARGET'..."
        docker compose exec -it "$TARGET" /bin/bash 2>/dev/null || docker compose exec -it "$TARGET" /bin/sh
        ;;
    "status")
        check_prerequisites
        docker compose ps
        ;;
    "backup")
        check_prerequisites
        TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
        BACKUP_DIR="${PROJECT_DIR}/backups"
        mkdir -p "$BACKUP_DIR"
        DB_NAME=$(grep -E "^DB_DATABASE=" .env | cut -d '=' -f2 | tr -d ' ' || echo "abdimas")
        DB_ROOT_PW=$(grep -E "^DB_ROOT_PASSWORD=" .env | cut -d '=' -f2 | tr -d ' ' || echo "root_secret")
        BACKUP_FILE="${BACKUP_DIR}/backup_${DB_NAME}_${TIMESTAMP}.sql"

        log_info "Creating database backup from Docker container..."
        docker compose exec -T db mysqldump -u root -p"${DB_ROOT_PW}" "${DB_NAME}" > "$BACKUP_FILE"
        log_success "Backup created: $BACKUP_FILE"
        ;;
    "restore")
        check_prerequisites
        if [ -z "$2" ]; then
            log_error "Usage: ./deploy.sh restore <path_to_sql_file>"
            exit 1
        fi
        SQL_FILE="$2"
        if [ ! -f "$SQL_FILE" ]; then
            log_error "File not found: $SQL_FILE"
            exit 1
        fi
        DB_NAME=$(grep -E "^DB_DATABASE=" .env | cut -d '=' -f2 | tr -d ' ' || echo "abdimas")
        DB_ROOT_PW=$(grep -E "^DB_ROOT_PASSWORD=" .env | cut -d '=' -f2 | tr -d ' ' || echo "root_secret")

        log_warn "Restoring database from $SQL_FILE..."
        docker compose exec -T db mysql -u root -p"${DB_ROOT_PW}" "${DB_NAME}" < "$SQL_FILE"
        log_success "Database restored successfully."
        ;;
    *)
        echo "Usage: ./deploy.sh [command]"
        echo ""
        echo "Commands:"
        echo "  deploy          (default) Build and deploy the entire Docker stack"
        echo "  deploy --seed   Deploy and execute database seeders"
        echo "  seed            Execute database seeders on running app"
        echo "  migrate         Run database migrations"
        echo "  stop / down     Stop and remove Docker containers"
        echo "  restart         Restart Docker containers"
        echo "  logs [service]  Stream logs (service: app or db)"
        echo "  shell [service] Open bash inside container (service: app or db)"
        echo "  status          Show container status"
        echo "  backup          Export MySQL database dump into backups/ directory"
        echo "  restore <file>  Import SQL file into MySQL container database"
        ;;
esac
