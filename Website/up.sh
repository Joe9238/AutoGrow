#!/bin/bash
# Start Sail containers
wt.exe new-tab wsl --cd "$(pwd)" ./vendor/bin/sail up

# Wait for containers to be up
sleep 15

# Install npm dependencies inside the container
wt.exe new-tab wsl --cd "$(pwd)" ./vendor/bin/sail npm install

# Install composer dependencies inside the container
wt.exe new-tab wsl --cd "$(pwd)" ./vendor/bin/sail composer install

# Wait for everything to settle
sleep 25

# Start Vite dev server inside the container
wt.exe new-tab wsl --cd "$(pwd)" ./vendor/bin/sail npm run dev

# Run migrations inside the container
wt.exe new-tab wsl --cd "$(pwd)" ./vendor/bin/sail artisan migrate