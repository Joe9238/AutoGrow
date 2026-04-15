#!/bin/bash

SESSION="laravel"

# Kill existing session if it exists
tmux has-session -t $SESSION 2>/dev/null
if [ $? -eq 0 ]; then
    tmux kill-session -t $SESSION
fi

tmux new-session -d -s $SESSION

# Window 1 - Sail
tmux rename-window -t $SESSION "sail"
tmux send-keys -t $SESSION "./vendor/bin/sail up" C-m

# Wait for containers to be up
sleep 15

# Window 2 - npm install
tmux new-window -t $SESSION -n "npm"
tmux send-keys -t $SESSION "./vendor/bin/sail npm install --verbose" C-m

# Window 3 - composer install
tmux new-window -t $SESSION -n "composer"
tmux send-keys -t $SESSION "./vendor/bin/sail composer install" C-m

# Wait for everything to settle
sleep 25

# Window 4 - Vite
tmux new-window -t $SESSION -n "vite"
tmux send-keys -t $SESSION "./vendor/bin/sail npm run dev" C-m

# Window 5 - migrations
tmux new-window -t $SESSION -n "artisan"
tmux send-keys -t $SESSION "./vendor/bin/sail artisan migrate" C-m

# Window 6 - mqtt listener
tmux new-window -t $SESSION -n "mqtt"
tmux send-keys -t $SESSION "./vendor/bin/sail artisan mqtt:listen" C-m

# window 7 - scheduler
tmux new-window -t $SESSION -n "scheduler"
tmux send-keys -t $SESSION "./vendor/bin/sail artisan schedule:work" C-m

# Window 8 - queue worker
tmux new-window -t $SESSION -n "queue"
tmux send-keys -t $SESSION "./vendor/bin/sail artisan queue:work" C-m

# Optional seed
# tmux send-keys -t $SESSION "./vendor/bin/sail artisan db:seed" C-m

tmux attach -t $SESSION