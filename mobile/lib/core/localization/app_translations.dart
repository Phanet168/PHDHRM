/// Looks up [key] in the loaded Laravel [language] map, falling back to
/// [fallback] when the key is missing or blank.
///
/// Shared across pages so every screen resolves translations the same way
/// instead of each page keeping its own copy of this lookup.
String tr(Map<String, String> language, String key, String fallback) {
  final value = language[key]?.trim();
  if (value == null || value.isEmpty) {
    return fallback;
  }

  return value;
}
