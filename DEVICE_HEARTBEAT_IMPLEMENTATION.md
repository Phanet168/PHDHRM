# Staff Online Status - Device Heartbeat Implementation

## Problem
Staff devices were never appearing as "online" even when the mobile app was running.

## Root Cause
The Flutter mobile app never called the `/auth/device-heartbeat` API endpoint, so the backend never updated the device's `last_login_at` timestamp. The backend uses this timestamp to determine if a device is online (within the last 10 minutes).

## Solution
Implemented a `DeviceHeartbeatService` in Flutter that:
1. Automatically calls `/auth/device-heartbeat` every 5 minutes
2. Starts immediately after successful login
3. Resumes when the app session is restored
4. Stops on logout

## Files Changed

### 1. New File: `mobile/lib/core/network/device_heartbeat_service.dart`
- DeviceHeartbeatService class
- Manages periodic heartbeat calls to backend
- Handles errors gracefully without affecting app

### 2. Updated: `mobile/lib/features/auth/controllers/auth_controller.dart`
- Added DeviceHeartbeatService injection
- Start heartbeat after successful login
- Start heartbeat when session is restored
- Stop heartbeat on logout
- Added dispose method to clean up resources

## How It Works

```
User Login
    ↓
AuthController.login() 
    ↓
Token saved + User stored
    ↓
DeviceHeartbeatService.start()
    ↓
Send heartbeat immediately
    ↓
Schedule heartbeats every 5 minutes
    ↓
Backend updates mobile_device_registrations.last_login_at
    ↓
Device appears ONLINE in admin dashboard
```

## Backend Integration
Backend already had the `/auth/device-heartbeat` endpoint implemented:
- POST endpoint at `/auth/device-heartbeat`
- Updates `mobile_device_registrations.last_login_at = now()`
- Returns device status and last_seen_at timestamp

## Heartbeat Details
- **Interval:** 5 minutes (configurable via constructor)
- **Start:** Immediately on login, then every 5 minutes
- **Failure Handling:** Errors are logged but don't affect app operation
- **Data Sent:** device_id, device_name, platform

## Testing
After deployment:
1. Login on mobile app
2. Check admin dashboard - device should show as ONLINE
3. Device stays ONLINE while app is running (or backgrounded for 10 minutes)
4. Device goes OFFLINE after 10 minutes with no heartbeat
5. Logout stops the heartbeat service

## Future Improvements
- [ ] Add background task to continue heartbeat when app is backgrounded
- [ ] Add user notification if device is marked offline
- [ ] Add manual heartbeat retry on network reconnection
- [ ] Add configuration UI for heartbeat interval
