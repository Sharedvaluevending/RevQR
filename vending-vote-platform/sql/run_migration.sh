#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# MySQL credentials
MYSQL_USER="root"
MYSQL_PASS="root"  # Using root as password

echo -e "${GREEN}Starting migration process...${NC}\n"

# 1. Backup database
echo "1. Creating database backup..."
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="revenueqr_backup_${TIMESTAMP}.sql"

if mysqldump -u "$MYSQL_USER" -p"$MYSQL_PASS" revenueqr > "sql/${BACKUP_FILE}"; then
    echo -e "${GREEN}✓ Backup created: sql/${BACKUP_FILE}${NC}"
else
    echo -e "${RED}✗ Backup failed! Aborting migration.${NC}"
    exit 1
fi

# 2. Run migration
echo -e "\n2. Running migration..."
if php sql/migrate.php; then
    echo -e "${GREEN}✓ Migration completed${NC}"
else
    echo -e "${RED}✗ Migration failed! Rolling back...${NC}"
    php sql/rollback.php
    exit 1
fi

# 3. Verify migration
echo -e "\n3. Verifying migration..."
if php sql/verify_migration.php; then
    echo -e "${GREEN}✓ Verification passed${NC}"
else
    echo -e "${RED}✗ Verification failed! Rolling back...${NC}"
    php sql/rollback.php
    exit 1
fi

echo -e "\n${GREEN}Migration process completed successfully!${NC}"
echo "Backup file: sql/${BACKUP_FILE}" 