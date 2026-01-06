#!/bin/bash

# Remove Queue from Email Classes
# This makes emails send immediately instead of being queued
# USE ONLY FOR DEVELOPMENT/TESTING!

echo "=========================================="
echo "Remove Queue from Email Classes"
echo "=========================================="
echo ""
echo "⚠️  WARNING: This is for DEVELOPMENT only!"
echo "⚠️  For PRODUCTION, use queue worker instead!"
echo ""
read -p "Continue? (y/n) " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]
then
    echo "Cancelled."
    exit 1
fi

echo ""
echo "Removing 'implements ShouldQueue' from Mail classes..."
echo ""

# List of mail classes
MAIL_CLASSES=(
    "app/Mail/AppointmentConfirmationMail.php"
    "app/Mail/NewAppointmentNotificationMail.php"
    "app/Mail/AppointmentCancelledMail.php"
    "app/Mail/AppointmentReminderMail.php"
    "app/Mail/ReviewRequestMail.php"
    "app/Mail/DailyReportMail.php"
    "app/Mail/SalonApprovedMail.php"
)

for file in "${MAIL_CLASSES[@]}"; do
    if [ -f "$file" ]; then
        echo "Processing: $file"

        # Remove 'implements ShouldQueue'
        sed -i 's/ implements ShouldQueue//g' "$file"

        # Remove 'use Illuminate\Contracts\Queue\ShouldQueue;'
        sed -i '/use Illuminate\\Contracts\\Queue\\ShouldQueue;/d' "$file"

        echo "  ✓ Removed queue from $file"
    else
        echo "  ✗ File not found: $file"
    fi
done

echo ""
echo "=========================================="
echo "Done!"
echo "=========================================="
echo ""
echo "Emails will now be sent IMMEDIATELY instead of being queued."
echo ""
echo "⚠️  IMPORTANT:"
echo "  - This will slow down appointment creation"
echo "  - Use only for development/testing"
echo "  - For production, use queue worker instead"
echo ""
echo "To revert, run: git checkout app/Mail/"
echo ""
