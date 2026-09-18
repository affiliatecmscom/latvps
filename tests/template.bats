#!/usr/bin/env bats
# Bất biến của template compose: giới hạn tài nguyên có đủ, và db/redis/php TUYỆT ĐỐI
# không được publish cổng ra host. Test này chặn việc ai đó vô tình thêm 'ports:'.

load helper
setup() {
  load_lat
  RENDERED="${BATS_TEST_TMPDIR}/compose.yml"
  render_template "${LAT_ROOT}/templates/wordpress/compose.yml.tmpl" \
    "ID=s-abc123" "DOMAIN=my-deals.com" > "$RENDERED"
}

# Đọc compose bằng python (thay ${VAR} bằng placeholder như compose sẽ interpolate).
svc_field() {
  python3 - "$RENDERED" "$1" "$2" <<'PY'
import sys, re, yaml
raw = open(sys.argv[1]).read()
raw = re.sub(r'\$\{([A-Z_]+)(:-[^}]*)?\}', 'PLACEHOLDER', raw)
svc = yaml.safe_load(raw)['services'][sys.argv[2]]
v = svc.get(sys.argv[3], '')
print(v if not isinstance(v, (dict, list)) else 'YES')
PY
}

@test "template: render ra YAML hợp lệ, đủ 4 service" {
  run python3 -c "
import re,yaml,sys
raw=open('$RENDERED').read()
raw=re.sub(r'\\\$\{([A-Z_]+)(:-[^}]*)?\}','PLACEHOLDER',raw)
print(','.join(sorted(yaml.safe_load(raw)['services'])))
"
  [ "$status" -eq 0 ]
  [ "$output" = "db,php,redis,web" ]
}

@test "template: token __ID__/__DOMAIN__ đã được thay hết" {
  ! grep -q '__[A-Z]*__' "$RENDERED"
  grep -q 'name: site-s-abc123' "$RENDERED"
  grep -q 'container_name: s-abc123_db' "$RENDERED"
}

@test "template: \${DB_PASSWORD}/\${REDIS_PASSWORD} GIỮ NGUYÊN cho compose đọc lúc chạy" {
  grep -q 'MARIADB_PASSWORD: ${DB_PASSWORD}' "$RENDERED"
  grep -q '\-\-requirepass","${REDIS_PASSWORD}"' "$RENDERED"
}

@test "BẢO MẬT: db/redis/php KHÔNG được publish cổng ra host" {
  for s in db redis php; do
    run svc_field "$s" ports
    [ -z "$output" ] || { echo "service $s có ports: -> LỘ RA HOST"; false; }
  done
}

@test "template: mọi service đều có mem_limit VÀ cpus" {
  for s in db redis php web; do
    run svc_field "$s" mem_limit
    [ -n "$output" ] || { echo "$s thiếu mem_limit"; false; }
    run svc_field "$s" cpus
    [ -n "$output" ] || { echo "$s thiếu cpus"; false; }
  done
}

@test "template: mọi service đều có no-new-privileges" {
  [ "$(grep -c 'no-new-privileges:true' "$RENDERED")" -eq 4 ]
}

@test "template: db + redis có healthcheck" {
  for s in db redis; do
    run svc_field "$s" healthcheck
    [ "$output" = "YES" ] || { echo "$s thiếu healthcheck"; false; }
  done
}

@test "template: chỉ web join network proxy" {
  run svc_field web networks
  [ "$output" = "YES" ]
  # db/redis/php chỉ có [internal] -> file không được có 'proxy' trong dòng networks của chúng
  ! grep -E '^\s+networks: \[ internal, proxy \]' "$RENDERED" | grep -qv web
  [ "$(grep -c 'networks: \[ internal \]' "$RENDERED")" -eq 3 ]
}
