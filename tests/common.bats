#!/usr/bin/env bats
# Test các hàm THUẦN của lib/common.sh - chạy được trên CI, không cần Docker/VPS.

load helper

setup() { load_lat; }

# ---------- slugify ----------
@test "slugify: domain -> slug an toàn cho tên container" {
  run slugify "best-deals.example.com"
  [ "$status" -eq 0 ]
  [ "$output" = "best-deals-example-com" ]
}

@test "slugify: hạ chữ hoa + gộp dấu gạch thừa + cắt gạch đầu/cuối" {
  run slugify "..My__SITE!!.COM.."
  [ "$output" = "my-site-com" ]
}

# ---------- valid_domain ----------
@test "valid_domain: nhận domain hợp lệ" {
  for d in example.com sub.example.com a-b.co.uk x1.vn; do
    run valid_domain "$d"
    [ "$status" -eq 0 ] || { echo "từ chối nhầm: $d"; false; }
  done
}

@test "valid_domain: từ chối domain không hợp lệ" {
  # localhost (không có chấm), chữ hoa, gạch đầu/cuối, rỗng, có dấu /, có khoảng trắng
  for d in localhost "EXAMPLE.COM" "-bad.com" "bad-.com" "" "a.com/x" "a b.com" "a..com"; do
    run valid_domain "$d"
    [ "$status" -ne 0 ] || { echo "nhận nhầm: '$d'"; false; }
  done
}

# ---------- render_template ----------
@test "render_template: thay __KEY__ nhưng GIỮ NGUYÊN \${VAR} cho docker compose" {
  local t="${BATS_TEST_TMPDIR}/t.tmpl"
  printf 'name: site-__ID__\nhost: __DOMAIN__\npass: ${DB_PASSWORD}\n' > "$t"
  run render_template "$t" "ID=s-abc123" "DOMAIN=my-deals.com"
  [ "$status" -eq 0 ]
  [[ "$output" == *"name: site-s-abc123"* ]]
  [[ "$output" == *"host: my-deals.com"* ]]
  # ${DB_PASSWORD} phải còn nguyên - compose đọc nó từ .env lúc chạy.
  [[ "$output" == *'pass: ${DB_PASSWORD}'* ]]
}

@test "render_template: giá trị chứa / & | không phá lệnh sed" {
  local t="${BATS_TEST_TMPDIR}/t2.tmpl"
  printf 'v=__VAL__\n' > "$t"
  run render_template "$t" 'VAL=a/b&c|d'
  [ "$status" -eq 0 ]
  [ "$output" = 'v=a/b&c|d' ]
}

@test "render_template: thiếu file -> trả 1, KHÔNG exit (caller còn chạy được rollback)" {
  run render_template "${BATS_TEST_TMPDIR}/khong-ton-tai.tmpl" "ID=x"
  [ "$status" -eq 1 ]
}

# ---------- registry: site_set / site_get ----------
@test "site_set/site_get: ghi rồi đọc lại, ghi đè không nhân đôi dòng" {
  mkdir -p "${SITES_ROOT}/s-aaa111"
  site_set s-aaa111 DOMAIN "one.com"
  site_set s-aaa111 DOMAIN "two.com"
  run site_get s-aaa111 DOMAIN
  [ "$output" = "two.com" ]
  [ "$(grep -c '^DOMAIN=' "${SITES_ROOT}/s-aaa111/site.conf")" -eq 1 ]
}

@test "site_set: giá trị chứa ký tự đặc biệt của sed" {
  mkdir -p "${SITES_ROOT}/s-bbb222"
  site_set s-bbb222 NOTE "a/b&c|d"
  site_set s-bbb222 NOTE "x/y&z|w"
  run site_get s-bbb222 NOTE
  [ "$output" = "x/y&z|w" ]
}

# ---------- list_site_ids ----------
@test "list_site_ids: chỉ trả thư mục THẬT, bỏ symlink domain (không đếm trùng)" {
  mk_site s-aaa111 one.com
  mk_site s-bbb222 two.com
  run list_site_ids
  [ "$(echo "$output" | wc -l)" -eq 2 ]
  [[ "$output" == *"s-aaa111"* ]]
  [[ "$output" == *"s-bbb222"* ]]
  [[ "$output" != *"one.com"* ]]
}

# ---------- resolve_site (BUG 04aefca: site zombie) ----------
@test "resolve_site: nhận ID -> trả chính ID" {
  mk_site s-aaa111 one.com
  run resolve_site s-aaa111
  [ "$output" = "s-aaa111" ]
}

@test "resolve_site: nhận DOMAIN -> trả ID, KHÔNG BAO GIỜ trả domain" {
  # Đây là bug đã gây site zombie + domain bị chiếm vĩnh viễn (commit 04aefca):
  # /opt/sites/<domain> là symlink nên site_conf(<domain>) cũng tồn tại -> nhánh tắt
  # từng trả về "one.com" thay vì "s-aaa111" -> wp_run gọi container "one.com_php"
  # (không có), và site_remove xoá symlink trước rồi rm -rf thành no-op.
  mk_site s-aaa111 one.com
  run resolve_site one.com
  [ "$status" -eq 0 ]
  [ "$output" = "s-aaa111" ]
  [ "$output" != "one.com" ]
}

@test "resolve_site: domain lạ -> trả khác 0" {
  mk_site s-aaa111 one.com
  run resolve_site khong-co.com
  [ "$status" -ne 0 ]
}

@test "resolve_site: symlink trỏ hụt (site đã xoá) -> không nhận nhầm" {
  ln -sfn "${SITES_ROOT}/s-da-xoa" "${SITES_ROOT}/mo-coi.com"
  run resolve_site mo-coi.com
  [ "$status" -ne 0 ]
}

@test "site_id_by_domain: phân biệt đúng giữa nhiều site" {
  mk_site s-aaa111 one.com
  mk_site s-bbb222 two.com
  run site_id_by_domain two.com
  [ "$output" = "s-bbb222" ]
}

# ---------- new_site_id ----------
@test "new_site_id: đúng định dạng s-<6 hex> và không trùng nhau" {
  run new_site_id
  [[ "$output" =~ ^s-[0-9a-f]{6}$ ]]
  [ "$(new_site_id)" != "$(new_site_id)" ]
}
