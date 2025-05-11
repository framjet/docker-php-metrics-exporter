#!/bin/sh
# vim:sw=4:ts=4:et

set -e

entrypoint_log() {
    if [ -z "${DOCKER_ENTRYPOINT_QUIET_LOGS:-}" ]; then
        echo "$@"
    fi
}

# Use environment variables with defaults
PHP_METRICS_SCRIPT_DIR="${PHP_METRICS_SCRIPT_DIR:-/shared}"
PHP_METRICS_SCRIPT_FILE="$PHP_METRICS_SCRIPT_DIR/metrics.php"
PHP_METRICS_SCRIPT_DOWNLOAD_URL="${PHP_METRICS_SCRIPT_DOWNLOAD_URL:-https://raw.githubusercontent.com/framjet/docker-php-metrics-exporter/refs/heads/main/metrics.php}"

# Check if directory is mounted to container if so download the script
if [ -d "$PHP_METRICS_SCRIPT_DIR" ]; then
    entrypoint_log "Directory $PHP_METRICS_SCRIPT_DIR exists"

    # Check if the directory is writable
    if [ -w "$PHP_METRICS_SCRIPT_DIR" ]; then
        entrypoint_log "Directory $PHP_METRICS_SCRIPT_DIR is writable"

        # Always download the latest version, overwriting existing file
        entrypoint_log "Downloading latest metrics.php from $PHP_METRICS_SCRIPT_DOWNLOAD_URL"

        # Create a temporary file for download
        TEMP_FILE=$(mktemp)

        # Download the file using curl or wget
        if command -v curl >/dev/null 2>&1; then
            curl -fsSL -o "$TEMP_FILE" "$PHP_METRICS_SCRIPT_DOWNLOAD_URL"
        elif command -v wget >/dev/null 2>&1; then
            wget -q -O "$TEMP_FILE" "$PHP_METRICS_SCRIPT_DOWNLOAD_URL"
        else
            entrypoint_log "ERROR: Neither curl nor wget is available. Cannot download the file."
            rm -f "$TEMP_FILE"
            exit 1
        fi

        # Check if download was successful
        if [ $? -eq 0 ] && [ -s "$TEMP_FILE" ]; then
            # Move the temp file to the final location (atomic operation)
            mv "$TEMP_FILE" "$PHP_METRICS_SCRIPT_FILE"
            # Set permissions to read
            chmod 644 "$PHP_METRICS_SCRIPT_FILE"

            entrypoint_log "Successfully downloaded and updated $PHP_METRICS_SCRIPT_FILE"
        else
            entrypoint_log "ERROR: Failed to download metrics.php"
            rm -f "$TEMP_FILE"
            exit 1
        fi
    else
        entrypoint_log "ERROR: Directory $PHP_METRICS_SCRIPT_DIR is not writable"
        exit 1
    fi
else
    entrypoint_log "WARNING: Directory $PHP_METRICS_SCRIPT_DIR does not exist. Volume may not be mounted."
fi
