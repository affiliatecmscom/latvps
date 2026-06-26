#!/usr/bin/env bash
# actions/cloudflare.sh — bật/tắt ACME DNS-01 qua Cloudflare để được bật proxy (đám mây cam).
# Bật: lưu CF_API_TOKEN vào caddy/.env, Caddyfile thêm acme_dns, recreate Caddy.

_cf_env_set() {
  local token="$1" f="${WPF_ROOT}/caddy/.env"
  touch "$f"; chmod 600 "$f"
  sed -i '/^CF_API_TOKEN=/d' "$f"
  printf 'CF_API_TOKEN=%s\n' "$token" >> "$f"
}

_cf_env_clear() {
  local f="${WPF_ROOT}/caddy/.env"
  [ -f "$f" ] && sed -i '/^CF_API_TOKEN=/d' "$f"
}

# Recreate Caddy để nạp env mới (đổi env phải recreate, reload không đủ).
_cf_recreate_caddy() {
  caddy_compose up -d --force-recreate >/dev/null 2>&1
}

# Verify token qua Cloudflare API (active + success).
_cf_verify_token() {
  local token="$1" resp
  resp="$(curl -fsS --max-time 15 -H "Authorization: Bearer ${token}" \
    https://api.cloudflare.com/client/v4/user/tokens/verify 2>/dev/null)" || return 1
  printf '%s' "$resp" | grep -q '"success":true' \
    && printf '%s' "$resp" | grep -q '"status":"active"'
}

_cf_enable() {
  ui_msg "Tạo Cloudflare API token:\n\n1. Cloudflare > My Profile > API Tokens > Create Token\n2. Template 'Edit zone DNS' (hoặc tự cấp: Zone.DNS = Edit + Zone.Zone = Read)\n3. Áp cho các zone (domain) bạn dùng\n4. Copy token, dán ở bước sau."
  local t; t="$(ui_input "Dán Cloudflare API token:" "")" || return 0
  t="$(printf '%s' "$t" | tr -d '[:space:]')"
  [ -n "$t" ] || { warn "Bỏ trống — huỷ."; return 0; }
  info "Xác thực token với Cloudflare..."
  if ! _cf_verify_token "$t"; then
    ui_msg "Token không hợp lệ hoặc thiếu quyền.\nCần: Zone.Zone:Read + Zone.DNS:Edit."
    return 0
  fi
  _cf_env_set "$t"
  write_caddyfile
  info "Khởi động lại Caddy (nạp token)..."
  _cf_recreate_caddy || warn "Recreate Caddy gặp lỗi — kiểm 'docker logs wpfactory_caddy'."
  ui_msg "Đã BẬT Cloudflare DNS (ACME DNS-01).\n\nTrên Cloudflare cho mỗi domain:\n  • Bật proxy (đám mây CAM)\n  • SSL/TLS mode = Full (strict)\n\nCert cấp qua DNS-01, không phụ thuộc proxy. Tạo site như bình thường."
}

_cf_disable() {
  ui_yesno "Tắt Cloudflare DNS? Sau đó dùng Let's Encrypt thường (domain phải để DNS-only / đám mây xám)." || return 0
  _cf_env_clear
  write_caddyfile
  info "Khởi động lại Caddy..."
  _cf_recreate_caddy || warn "Recreate Caddy gặp lỗi."
  ui_msg "Đã TẮT Cloudflare DNS.\n\nChuyển DNS các domain về DNS-only (đám mây XÁM) để cấp cert Let's Encrypt."
}

act_cloudflare() {
  require_root
  local cur; cur="$(caddy_env_get CF_API_TOKEN 2>/dev/null || true)"
  if [ -n "$cur" ]; then
    local c
    c="$(ui_menu "Cloudflare DNS đang BẬT. Làm gì?" \
      change "Đổi API token" \
      off    "Tắt Cloudflare DNS" \
      back   "Quay lại")" || return 0
    case "$c" in
      change) _cf_enable ;;
      off)    _cf_disable ;;
      *)      return 0 ;;
    esac
  else
    if ui_yesno "Bật Cloudflare DNS (ACME DNS-01) để bật proxy (đám mây cam) thoải mái?\n\nĐiều kiện: MỌI domain phải nằm trên Cloudflare dưới 1 API token."; then
      _cf_enable
    fi
  fi
}
