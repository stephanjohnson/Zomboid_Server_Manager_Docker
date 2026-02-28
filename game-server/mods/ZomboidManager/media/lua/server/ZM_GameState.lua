--
-- ZM_GameState.lua — Exports PZ game state (time, weather, season, temperature)
-- Writes to Lua/game_state.json every 1 minute via EveryOneMinute hook.
--

require("ZM_JSON")

ZM_GameState = {}

--- Get season name from the game time month.
--- PZ seasons: Spring (Mar-May), Summer (Jun-Aug), Autumn (Sep-Nov), Winter (Dec-Feb)
local function getSeason(month)
    if month >= 3 and month <= 5 then
        return "spring"
    elseif month >= 6 and month <= 8 then
        return "summer"
    elseif month >= 9 and month <= 11 then
        return "autumn"
    else
        return "winter"
    end
end

--- Export current game state to JSON file.
--- @return boolean success
function ZM_GameState.export()
    local gt = getGameTime()
    if not gt then
        return false
    end

    local ok1, year = pcall(function() return gt:getYear() end)
    local ok2, month = pcall(function() return gt:getMonth() end)
    local ok3, day = pcall(function() return gt:getDay() end)
    local ok4, hour = pcall(function() return gt:getHour() end)
    local ok5, minute = pcall(function() return gt:getMinutes() end)

    if not ok1 then year = 0 end
    if ok2 then month = month + 1 else month = 1 end
    if ok3 then day = day + 1 else day = 1 end
    if not ok4 then hour = 0 end
    if not ok5 then minute = 0 end

    local isNight = false
    local okN, nightVal = pcall(function() return gt:getNight() end)
    if okN and nightVal then isNight = nightVal > 0.5 end

    local state = {
        time = {
            year = year,
            month = month,
            day = day,
            hour = hour,
            minute = minute,
            day_of_year = 0,
            is_night = isNight,
            formatted = string.format("%02d:%02d", hour, minute),
            date = string.format("%04d-%02d-%02d", year, month, day),
        },
        season = getSeason(month),
    }

    local okT, now = pcall(os.time)
    if okT then
        state.exported_at = os.date("!%Y-%m-%dT%H:%M:%SZ", now)
    else
        state.exported_at = "unknown"
    end

    local encOk, jsonStr = pcall(json.encode, state)
    if not encOk then
        print("[ZomboidManager] GameState: JSON encode error: " .. tostring(jsonStr))
        return false
    end

    local writer = getFileWriter("game_state.json", true, false)
    if not writer then
        return false
    end

    writer:write(jsonStr)
    writer:close()
    return true
end
