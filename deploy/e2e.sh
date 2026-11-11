#!/usr/bin/env bash
set -u

BASE="https://62.109.12.44:8443"
CA="deploy/caddy-root.crt"
CRED="deploy/credentials.txt"
OUT="runtime/deploy-e2e"
JAR="$OUT/cookies.txt"
FAIL=0

mkdir -p "$OUT"
rm -f "$JAR"
set -a
. "$CRED"
set +a

req() {
  curl -sS --max-time 30 --cacert "$CA" "$@"
}

status() {
  local file="$1"
  shift
  req -b "$JAR" -c "$JAR" -o "$file" -w '%{http_code}' "$@"
}

csrf() {
  sed -n 's/.*name="_csrf" value="\([^"]*\)".*/\1/p' "$1" | head -1
}

check_eq() {
  local label="$1" expected="$2" actual="$3"
  if [ "$expected" = "$actual" ]; then
    printf 'PASS %s (%s)\n' "$label" "$actual"
  else
    printf 'FAIL %s expected=%s actual=%s\n' "$label" "$expected" "$actual"
    FAIL=1
  fi
}

check_contains() {
  local label="$1" needle="$2" file="$3"
  if grep -Fqi "$needle" "$file"; then
    printf 'PASS %s\n' "$label"
  else
    printf 'FAIL %s missing=%s\n' "$label" "$needle"
    FAIL=1
  fi
}

check_not_contains() {
  local label="$1" needle="$2" file="$3"
  if grep -Fqi "$needle" "$file"; then
    printf 'FAIL %s unexpected=%s\n' "$label" "$needle"
    FAIL=1
  else
    printf 'PASS %s\n' "$label"
  fi
}

# Unauthenticated access.
UNAUTH=$(status "$OUT/unauth-keywords.html" "$BASE/keywords")
check_eq "unauth /keywords redirect" "302" "$UNAUTH"

# Login page and CSRF.
LOGIN_PAGE=$(status "$OUT/login.html" "$BASE/login")
check_eq "login page" "200" "$LOGIN_PAGE"
TOKEN=$(csrf "$OUT/login.html")
if [ -z "$TOKEN" ]; then echo "FAIL login csrf token missing"; exit 1; fi
check_contains "login is russian" "Вход" "$OUT/login.html"

LOGIN_CODE=$(req -b "$JAR" -c "$JAR" -D "$OUT/login.headers" -o "$OUT/login-post.html" -w '%{http_code}' \
  --data-urlencode "_csrf=$TOKEN" \
  --data-urlencode "LoginForm[email]=$APP_ADMIN_EMAIL" \
  --data-urlencode "LoginForm[password]=$APP_ADMIN_PASSWORD" \
  "$BASE/login")
check_eq "login POST" "302" "$LOGIN_CODE"
if grep -Eiq '^set-cookie:.*secure' "$OUT/login.headers"; then
  echo "PASS session cookie Secure"
else
  echo "FAIL session cookie Secure missing"
  FAIL=1
fi
if grep -qi '^X-Debug' "$OUT/login.headers"; then
  echo "FAIL debug header present"
  FAIL=1
else
  echo "PASS no debug header"
fi

DASH=$(status "$OUT/dashboard.html" "$BASE/")
check_eq "dashboard" "200" "$DASH"
check_contains "dashboard pipeline blurb" "Импорт четырёх источников" "$OUT/dashboard.html"
check_contains "html lang ru" 'lang="ru"' "$OUT/dashboard.html"
TOKEN=$(csrf "$OUT/dashboard.html")

# CSRF rejection.
CSRF_CODE=$(req -b "$JAR" -c "$JAR" -o "$OUT/csrf-rejected.html" -w '%{http_code}' \
  -d "SettingsForm[minVolume]=50" "$BASE/settings")
check_eq "CSRF missing rejected" "400" "$CSRF_CODE"

# E2E source files.
cat > "$OUT/e2e.csv" <<'EOF'
keyword,volume,language,url
e2e csv clean keyword,120,en,https://site.pro/
e2e csv site.pro brand,90,en,https://site.pro/
e2e csv casino spam,80,en,https://site.pro/
EOF
cat > "$OUT/e2e.json" <<'EOF'
{"items":[{"query":"e2e json clean keyword","impressions":150,"language":"en_US","landing_page":"https://site.pro/"}]}
EOF
printf '{"items": [' > "$OUT/invalid.json"
cat > "$OUT/invalid.csv" <<'EOF'
keyword,language
e2e invalid csv,en
EOF

CSV_CODE=$(req -b "$JAR" -c "$JAR" -o "$OUT/upload-csv-result.html" -w '%{http_code}' \
  -F "_csrf=$TOKEN" -F "ImportForm[source]=search_console" \
  -F "ImportForm[file]=@$OUT/e2e.csv;type=text/csv" "$BASE/imports")
check_eq "CSV upload" "302" "$CSV_CODE"
IMPORTS=$(status "$OUT/imports.html" "$BASE/imports")
check_eq "imports page" "200" "$IMPORTS"
check_contains "CSV import flash" "Импортировано" "$OUT/imports.html"
TOKEN=$(csrf "$OUT/imports.html")

JSON_CODE=$(req -b "$JAR" -c "$JAR" -o "$OUT/upload-json-result.html" -w '%{http_code}' \
  -F "_csrf=$TOKEN" -F "ImportForm[source]=ahrefs_paid" \
  -F "ImportForm[file]=@$OUT/e2e.json;type=application/json" "$BASE/imports")
check_eq "JSON upload" "302" "$JSON_CODE"
IMPORTS=$(status "$OUT/imports-after-json.html" "$BASE/imports")
check_eq "imports after JSON" "200" "$IMPORTS"
check_contains "JSON import flash" "Импортировано" "$OUT/imports-after-json.html"
TOKEN=$(csrf "$OUT/imports-after-json.html")

# Invalid JSON and invalid CSV.
BADJSON=$(req -b "$JAR" -c "$JAR" -o "$OUT/invalid-json.html" -w '%{http_code}' \
  -F "_csrf=$TOKEN" -F "ImportForm[source]=search_console" \
  -F "ImportForm[file]=@$OUT/invalid.json;type=application/json" "$BASE/imports")
check_eq "invalid JSON" "422" "$BADJSON"
check_contains "invalid JSON message" "Некорректный JSON" "$OUT/invalid-json.html"

BADCSV=$(req -b "$JAR" -c "$JAR" -o "$OUT/invalid-csv.html" -w '%{http_code}' \
  -F "_csrf=$TOKEN" -F "ImportForm[source]=search_console" \
  -F "ImportForm[file]=@$OUT/invalid.csv;type=text/csv" "$BASE/imports")
check_eq "invalid CSV" "422" "$BADCSV"
check_contains "invalid CSV message" "Отсутствуют обязательные столбцы" "$OUT/invalid-csv.html"

# Cleaning status visibility.
KW=$(status "$OUT/keywords.html" "$BASE/keywords?q=e2e")
check_eq "keywords page" "200" "$KW"
check_contains "cleaning brand reason" "Брендовое слово" "$OUT/keywords.html"
check_contains "cleaning forbidden reason" "Запрещённое слово" "$OUT/keywords.html"

# Generate and preview.
PREVIEW_PAGE=$(status "$OUT/preview-before.html" "$BASE/campaigns/preview")
check_eq "preview before generation" "200" "$PREVIEW_PAGE"
TOKEN=$(csrf "$OUT/preview-before.html")
GEN=$(req -b "$JAR" -c "$JAR" -o "$OUT/generate-result.html" -w '%{http_code}' \
  -d "_csrf=$TOKEN" "$BASE/campaigns/generate")
check_eq "campaign generate" "302" "$GEN"
PREVIEW=$(status "$OUT/preview.html" "$BASE/campaigns/preview")
check_eq "preview after generation" "200" "$PREVIEW"
check_contains "preview russian" "Предпросмотр кампании" "$OUT/preview.html"
check_contains "preview structure check" "Проверка структуры CSV пройдена" "$OUT/preview.html"

# Export.
EXPORT_CODE=$(status "$OUT/google-ads.csv" "$BASE/campaigns/export")
check_eq "CSV export" "200" "$EXPORT_CODE"
check_contains "export RSA type" "Responsive search ad" "$OUT/google-ads.csv"
check_contains "export English target" "https://site.pro/Website-Builder/" "$OUT/google-ads.csv"
check_contains "export German target" "https://site.pro/de/" "$OUT/google-ads.csv"
COLS=$(head -1 "$OUT/google-ads.csv" | awk -F, '{print NF}')
check_eq "export columns" "26" "$COLS"

# Protected/non-public paths.
for path in .env data/db.sqlite vendor/autoload.php runtime/logs/app.log tests/bootstrap.php; do
  CODE=$(req -o "$OUT/blocked.html" -w '%{http_code}' "$BASE/$path")
  if [ "$CODE" = "404" ] || [ "$CODE" = "403" ]; then
    printf 'PASS blocked /%s (%s)\n' "$path" "$CODE"
  else
    printf 'FAIL blocked /%s (%s)\n' "$path" "$CODE"
    FAIL=1
  fi
done

printf '\nE2E_RESULT=%s\n' "$FAIL"
exit "$FAIL"
