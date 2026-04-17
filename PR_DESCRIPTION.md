## Summary

This PR restructures the repository into a monorepo layout with separate backend and mobile applications.

## What Changed

- Moved Laravel app into `backend/`
- Moved Flutter app into `mobile/`
- Updated root `.gitignore` for backend and mobile generated files
- Updated root `README.md` with new project structure and run instructions
- Added VS Code workspace tasks and launch config for backend/mobile workflows

## Why

- Clear separation of responsibilities between API/web backend and mobile frontend
- Easier onboarding and local development
- Cleaner git history by ignoring generated/runtime artifacts

## Validation

- Verified root structure contains:
  - `backend/`
  - `mobile/`
- Confirmed git ignore rules for:
  - backend `.env`, `vendor/`, `node_modules/`
  - mobile `.dart_tool/`, `build/`, `ios/Pods/`, `android/.gradle/`

## Notes

- Flutter dependency resolution requires a Flutter/Dart SDK version compatible with `mobile/pubspec.yaml`.
