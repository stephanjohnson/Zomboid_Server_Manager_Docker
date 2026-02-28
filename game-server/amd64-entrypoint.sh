#!/bin/bash
# Wrapper entrypoint for the AMD64 game server image (renegademaster).
# Copies ZomboidManager Lua bridge files into PZ's server script directory
# so they're loaded automatically during startup, bypassing the mod system
# which has discovery issues on PZ Build 42 dedicated servers.

SRC="/home/steam/Zomboid/mods/ZomboidManager/media/lua/server"
DST="/home/steam/ZomboidDedicatedServer/media/lua/server"

copy_bridge_files() {
    if [ -d "$SRC" ] && [ -d "$DST" ]; then
        cp "$SRC"/ZM_*.lua "$DST/" 2>/dev/null && \
            echo "[entrypoint] Copied ZomboidManager bridge files to PZ server scripts"
    fi
}

# Patch run_server.sh to copy bridge files after SteamCMD updates the game,
# ensuring our files are present even after validation overwrites them.
sed -i '/^update_server$/a copy_bridge_files' /home/steam/run_server.sh

# Export function and vars so they're available in the patched script
export -f copy_bridge_files
export SRC DST

exec /home/steam/run_server.sh
