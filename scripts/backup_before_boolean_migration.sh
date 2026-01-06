#!/bin/bash

# Boolean Migration - Pre-Migration Backup Script
# Created: 2024-12-28
# Purpose: Create full database backup before SMALLINT to BOOLEAN migration

set -e  # Exit on error

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/www/vhosts/frizerino.com/backups"
BACKUP_FILE="${BACKUP_DIR}/backup_before_boolean_${TIMESTAMP}.sql"
DB_USER="a0hcym59d1rhk"
DB_NAME="frizerinodb"
DB_HOST="localhost"

echo "=========================================="
echo "Boolean Migration - Database Backup"
echo "=========================================="
echo ""
echo "Timestamp: ${TIMESTAMP}"
echo "Database: ${DB_NAME}"
echo "Backup file: ${BACKUP_FILE}"
echo ""

# Create backup directory if it doesn't exist
mkdir -p "${BACKUP_DIR}"

# Create backup
echo "Creating backup..."
pg_dump -h "${DB_HOST}" -U "${DB_USER}" -d "${DB_NAME}" > "${BACKUP_FILE}"

# Verify backup
if [ -f "${BACKUP_FILE}" ]; then
    BACKUP_SIZE=$(du -h "${BACKUP_FILE}" | cut -f1)
    echo ""
    echo "✅ Backup created successfully!"
    echo "   File: ${BACKUP_FILE}"
    echo "   Size: ${BACKUP_SIZE}"
    echo ""
else
    echo ""
    echo "❌ Backup failed!"
    exit 1
fi

# Compress backup
echo "Compressing backup..."
gzip "${BACKUP_FILE}"

if [ -f "${BACKUP_FILE}.gz" ]; then
    COMPRESSED_SIZE=$(du -h "${BACKUP_FILE}.gz" | cut -f1)
    echo ""
    echo "✅ Backup compressed successfully!"
    echo "   File: ${BACKUP_FILE}.gz"
    echo "   Size: ${COMPRESSED_SIZE}"
    echo ""
else
    echo ""
    echo "⚠️  Compression failed, but uncompressed backup exists"
    echo ""
fi

echo "=========================================="
echo "Backup Complete"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Document current data counts (run verify script)"
echo "2. Test migration on staging (recommended)"
echo "3. Run production migration"
echo ""
