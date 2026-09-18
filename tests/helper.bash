#!/usr/bin/env bash
# tests/helper.bash - nạp lib của lat vào môi trường test, cô lập khỏi /opt thật.
# Chỉ source (không exec) nên không đụng Docker/UFW/host.

load_lat() {
  LAT_ROOT="$(cd "${BATS_TEST_DIRNAME}/.." && pwd)"
  # shellcheck source=/dev/null
  source "${LAT_ROOT}/lib/common.sh"
  # shellcheck source=/dev/null
  source "${LAT_ROOT}/lib/actions/update_check.sh"   # version_gt

  # CÔ LẬP: trỏ mọi thao tác registry vào thư mục tạm của bats, KHÔNG đụng /opt/sites thật.
  SITES_ROOT="${BATS_TEST_TMPDIR}/sites"
  BACKUPS_ROOT="${BATS_TEST_TMPDIR}/backups"
  mkdir -p "$SITES_ROOT" "$BACKUPS_ROOT"

  # info/ok/warn ghi ra stderr -> im lặng trong test cho gọn output.
  info() { :; }
  ok()   { :; }
  warn() { :; }
}

# Tạo 1 site giả trong SITES_ROOT (thư mục thật + site.conf), kèm symlink domain như lat làm.
mk_site() {
  local id="$1" domain="$2"
  mkdir -p "${SITES_ROOT}/${id}"
  printf 'ID=%s\nDOMAIN=%s\nTYPE=affiliatecms\n' "$id" "$domain" > "${SITES_ROOT}/${id}/site.conf"
  ln -sfn "${SITES_ROOT}/${id}" "${SITES_ROOT}/${domain}"
}
