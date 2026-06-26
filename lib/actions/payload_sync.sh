#!/usr/bin/env bash
# actions/payload_sync.sh — cập nhật plugin/theme bundle vào payload/ từ nguồn (demo hoặc release).
# Chỉ ảnh hưởng site affiliatecms tạo SAU. Site cũ update qua license server.

act_payload_sync() {
  require_root
  local src="${1:-/opt/demo-iflmmo/wp-content}"
  [ -d "$src" ] || { warn "Không thấy nguồn: $src (truyền đường dẫn wp-content)."; return 1; }
  local payload="${WPF_ROOT}/payload"
  mkdir -p "${payload}/plugins" "${payload}/themes" "${payload}/mu-plugins"

  local p
  for p in affiliatecms-pro affiliatecms-ai; do
    if [ -d "${src}/plugins/${p}" ]; then
      info "Sync plugin ${p}..."
      rsync -a --delete "${src}/plugins/${p}/" "${payload}/plugins/${p}/"
    else
      warn "Bỏ qua: thiếu plugin ${p} trong nguồn."
    fi
  done

  if [ -d "${src}/themes/affiliateCMS-theme" ]; then
    info "Sync theme cha..."
    rsync -a --delete "${src}/themes/affiliateCMS-theme/" "${payload}/themes/affiliateCMS-theme/"
  fi
  local tdir tname
  for tdir in "${src}/themes/"*/; do
    tname="$(basename "$tdir")"
    [ "$tname" = "affiliateCMS-theme" ] && continue
    if grep -qs 'Template:[[:space:]]*affiliateCMS-theme' "${tdir}style.css" 2>/dev/null; then
      info "Sync child theme ${tname}..."
      rsync -a --delete "${tdir}" "${payload}/themes/${tname}/"
    fi
  done

  [ -f "${src}/mu-plugins/proxy-ssl.php" ] && cp "${src}/mu-plugins/proxy-ssl.php" "${payload}/mu-plugins/proxy-ssl.php"
  ok "Payload đã cập nhật từ ${src}."
}
