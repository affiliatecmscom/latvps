#!/usr/bin/env bats
# Test hai lưới an toàn khi tải payload/wp-cli: verify_sha512 + unzip_replace_dir.

load helper
setup() { load_lat; }

# ---------- verify_sha512 ----------
@test "verify_sha512: hash đúng -> 0" {
  local f="${BATS_TEST_TMPDIR}/a.bin"
  printf 'noi dung that\n' > "$f"
  local h; h="$(sha512sum "$f" | awk '{print $1}')"
  run verify_sha512 "$f" "$h"
  [ "$status" -eq 0 ]
}

@test "verify_sha512: chấp nhận HOA + khoảng trắng thừa (file .sha512 hay có \\n)" {
  local f="${BATS_TEST_TMPDIR}/b.bin"
  printf 'x\n' > "$f"
  local h; h="$(sha512sum "$f" | awk '{print $1}' | tr 'a-f' 'A-F')"
  run verify_sha512 "$f" "  ${h}  "
  [ "$status" -eq 0 ]
}

@test "verify_sha512: file bị sửa 1 byte -> khác 0" {
  local f="${BATS_TEST_TMPDIR}/c.bin"
  printf 'goc\n' > "$f"
  local h; h="$(sha512sum "$f" | awk '{print $1}')"
  printf 'da bi sua\n' > "$f"
  run verify_sha512 "$f" "$h"
  [ "$status" -ne 0 ]
}

@test "verify_sha512: 'hash' là rác/HTML -> khác 0 (KHÔNG coi là hợp lệ)" {
  # Nếu server trả trang lỗi HTML thay vì file .sha512, không được vô tình pass.
  local f="${BATS_TEST_TMPDIR}/d.bin"
  printf 'x\n' > "$f"
  for bad in "" "not-a-hash" "<html>404</html>" "abc123"; do
    run verify_sha512 "$f" "$bad"
    [ "$status" -ne 0 ] || { echo "nhận nhầm hash rác: '$bad'"; false; }
  done
}

@test "verify_sha512: file không tồn tại -> khác 0" {
  run verify_sha512 "${BATS_TEST_TMPDIR}/khong-co" "$(printf 'a%.0s' {1..128})"
  [ "$status" -ne 0 ]
}

# ---------- unzip_replace_dir ----------
# Tạo zip bằng python3 (không cần lệnh `zip` trên máy CI).
mkzip() {
  local out="$1" top="$2" content="${3:-noi dung}"
  python3 - "$out" "$top" "$content" <<'PY'
import sys, zipfile
out, top, content = sys.argv[1], sys.argv[2], sys.argv[3]
with zipfile.ZipFile(out, 'w') as z:
    z.writestr(f"{top}/plugin.php", content)
    z.writestr(f"{top}/sub/file.txt", "x")
PY
}

@test "unzip_replace_dir: zip tốt -> thay được bản cũ" {
  local parent="${BATS_TEST_TMPDIR}/plugins"
  mkdir -p "${parent}/acms-pro"
  printf 'BAN CU\n' > "${parent}/acms-pro/plugin.php"
  mkzip "${BATS_TEST_TMPDIR}/p.zip" acms-pro "BAN MOI"
  run unzip_replace_dir "${BATS_TEST_TMPDIR}/p.zip" "$parent" acms-pro
  [ "$status" -eq 0 ]
  grep -q "BAN MOI" "${parent}/acms-pro/plugin.php"
  [ -f "${parent}/acms-pro/sub/file.txt" ]
}

@test "unzip_replace_dir: zip HỎNG -> GIỮ NGUYÊN bản cũ (bug: rm -rf trước khi unzip)" {
  # Đây là hồi quy cho bug thật: bản cũ rm -rf thư mục đang dùng RỒI mới unzip,
  # nên tải hỏng/server trả HTML = mất luôn plugin đang chạy được, offline thì hết đường.
  local parent="${BATS_TEST_TMPDIR}/plugins"
  mkdir -p "${parent}/acms-pro"
  printf 'BAN CU DANG CHAY\n' > "${parent}/acms-pro/plugin.php"
  printf '<html>404 Not Found</html>' > "${BATS_TEST_TMPDIR}/hong.zip"
  run unzip_replace_dir "${BATS_TEST_TMPDIR}/hong.zip" "$parent" acms-pro
  [ "$status" -ne 0 ]
  # Bản cũ PHẢI còn nguyên
  [ -f "${parent}/acms-pro/plugin.php" ]
  grep -q "BAN CU DANG CHAY" "${parent}/acms-pro/plugin.php"
}

@test "unzip_replace_dir: zip thiếu thư mục gốc đúng tên -> giữ nguyên bản cũ" {
  local parent="${BATS_TEST_TMPDIR}/plugins"
  mkdir -p "${parent}/acms-pro"
  printf 'BAN CU\n' > "${parent}/acms-pro/plugin.php"
  mkzip "${BATS_TEST_TMPDIR}/sai.zip" ten-khac "x"
  run unzip_replace_dir "${BATS_TEST_TMPDIR}/sai.zip" "$parent" acms-pro
  [ "$status" -ne 0 ]
  grep -q "BAN CU" "${parent}/acms-pro/plugin.php"
}

@test "unzip_replace_dir: không để lại thư mục .stage- rác khi lỗi" {
  local parent="${BATS_TEST_TMPDIR}/plugins"
  mkdir -p "$parent"
  printf 'rac' > "${BATS_TEST_TMPDIR}/hong.zip"
  run unzip_replace_dir "${BATS_TEST_TMPDIR}/hong.zip" "$parent" acms-pro
  [ "$status" -ne 0 ]
  [ -z "$(find "$parent" -maxdepth 1 -name '.stage-*' -print -quit)" ]
}
