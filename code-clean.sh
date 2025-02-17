#!/bin/sh

# Shell script to run phpcs and phpcbf with Drupal coding standards

# Check if path is provided
if [ "$#" -lt 1 ]; then
    echo "Usage: $0 <path-to-check> [-y]"
    exit 1
fi

# Path to check
PATH_TO_CHECK=$1

# Specify your PHPCS and PHPCBF paths
PHPCS_PATH="vendor/bin/phpcs"
PHPCBF_PATH="vendor/bin/phpcbf"

# Check if -y flag is set
AUTO_FIX=false
if [ "$#" -eq 2 ] && [ "$2" = "-y" ]; then
    AUTO_FIX=true
fi

# Run PHP CodeSniffer
echo "Running PHP CodeSniffer..."
$PHPCS_PATH --standard=Drupal,DrupalPractice --extensions=php,module,inc,install,test,profile,theme,info,yml $PATH_TO_CHECK

# Check if we should automatically fix the issues
if $AUTO_FIX; then
    echo "Running PHP Code Beautifier and Fixer..."
    $PHPCBF_PATH --standard=Drupal,DrupalPractice --extensions=php,module,inc,install,test,profile,theme,info,yml $PATH_TO_CHECK
else
    # Ask the user if they want to fix the errors automatically
    echo "Do you wish to run PHP Code Beautifier and Fixer on the specified path? (y/n)"
    read REPLY
    if [ "$REPLY" = "y" ] || [ "$REPLY" = "Y" ]; then
        # Run PHP Code Beautifier and Fixer
        echo "Running PHP Code Beautifier and Fixer..."
        $PHPCBF_PATH --standard=Drupal,DrupalPractice --extensions=php,module,inc,install,test,profile,theme,info,yml $PATH_TO_CHECK
    fi
fi

echo "Done!"
