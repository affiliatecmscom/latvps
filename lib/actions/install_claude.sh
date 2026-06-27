#!/usr/bin/env bash
# actions/install_claude.sh - cài Claude Code (CLI Anthropic) cho học viên trên VPS.
# Native installer (không cần Node). Tự thêm PATH (installer KHÔNG tự làm trên root trắng).
# Auth headless: ANTHROPIC_API_KEY hoặc CLAUDE_CODE_OAUTH_TOKEN.

CLAUDE_BIN="/root/.local/bin/claude"
CLAUDE_ENV_DIR="/root/.config/lat"
CLAUDE_ENV_FILE="${CLAUDE_ENV_DIR}/claude-env"
CLAUDE_BASHRC="/root/.bashrc"

# Đảm bảo ~/.local/bin trong PATH (.bashrc) + trong process hiện tại.
_claude_ensure_path() {
  grep -q '.local/bin' "$CLAUDE_BASHRC" 2>/dev/null \
    || printf '\n# Claude Code\nexport PATH="$HOME/.local/bin:$PATH"\n' >> "$CLAUDE_BASHRC"
  case ":$PATH:" in *":/root/.local/bin:"*) ;; *) export PATH="/root/.local/bin:$PATH";; esac
}

# Lưu biến auth vào file chmod 600 + nạp từ .bashrc.
_claude_save_env() {
  local var="$1" val="$2"
  mkdir -p "$CLAUDE_ENV_DIR"; chmod 700 "$CLAUDE_ENV_DIR"
  touch "$CLAUDE_ENV_FILE"
  sed -i "/^export ${var}=/d" "$CLAUDE_ENV_FILE" 2>/dev/null || true
  printf 'export %s=%q\n' "$var" "$val" >> "$CLAUDE_ENV_FILE"
  chmod 600 "$CLAUDE_ENV_FILE"
  local srcline='[ -f ~/.config/lat/claude-env ] && . ~/.config/lat/claude-env'
  grep -qF "$srcline" "$CLAUDE_BASHRC" 2>/dev/null \
    || printf '\n# Claude Code auth (lat)\n%s\n' "$srcline" >> "$CLAUDE_BASHRC"
  # nạp ngay cho process hiện tại
  export "${var}=${val}"
}

# Hỏi + lưu auth (bỏ qua được).
_claude_auth_prompt() {
  local choice
  choice="$(ui_menu "Đăng nhập Claude Code (VPS headless)" \
    oauth  "OAuth token - dùng gói Claude Pro/Max (khuyến nghị)" \
    apikey "API key sk-ant-... (console.anthropic.com, tính theo token)" \
    skip   "Bỏ qua, đăng nhập sau")" || return 0
  case "$choice" in
    oauth)
      # Claude Code đã cài SẴN trên VPS -> chạy 'claude setup-token' tại chỗ.
      # Nó in ra MỘT ĐƯỜNG LINK: mở link bằng trình duyệt đang đăng nhập Claude
      # (Pro/Max) trên máy bất kỳ, đồng ý, rồi làm theo hướng dẫn trên màn hình.
      _claude_ensure_path
      if [ ! -x "$CLAUDE_BIN" ] && ! command -v claude >/dev/null 2>&1; then
        warn "Chưa thấy claude - cài Claude Code trước rồi đăng nhập."; return 0
      fi
      ui_msg "Sắp chạy 'claude setup-token' ngay trên VPS.\n\nNó sẽ HIỆN MỘT ĐƯỜNG LINK. Hãy:\n 1) Copy link, mở bằng trình duyệt ĐANG ĐĂNG NHẬP Claude (Pro/Max).\n 2) Bấm Authorize/Đồng ý.\n 3) Làm theo hướng dẫn trên màn hình (dán mã nếu được hỏi).\n\nNhấn Enter để bắt đầu."
      "$CLAUDE_BIN" setup-token </dev/tty || warn "setup-token chưa xong - chạy lại bất cứ lúc nào: claude setup-token"
      # Nếu màn hình in ra token (sk-ant-oat...), cho lưu vào env để dùng kiểu headless.
      local t; t="$(ui_input "Nếu có hiện token 'sk-ant-oat...', dán vào đây để lưu (Enter=bỏ qua):" "")" || t=""
      t="$(printf '%s' "$t" | tr -d '[:space:]')"
      if [ -n "$t" ]; then
        sed -i '/^export ANTHROPIC_API_KEY=/d' "$CLAUDE_ENV_FILE" 2>/dev/null || true
        _claude_save_env CLAUDE_CODE_OAUTH_TOKEN "$t"; ok "Đã lưu OAuth token."
      else
        ok "Hoàn tất. Nếu setup-token đã đăng nhập thành công, 'claude' dùng được luôn."
      fi
      ;;
    apikey)
      local k; k="$(ui_input "Dán ANTHROPIC_API_KEY (sk-ant-...):" "")" || return 0
      k="$(printf '%s' "$k" | tr -d '[:space:]')"
      [ -n "$k" ] && { _claude_save_env ANTHROPIC_API_KEY "$k"; ok "Đã lưu API key."; } || warn "Bỏ trống - bỏ qua."
      ;;
    *) info "Bỏ qua auth. Đăng nhập sau: set ANTHROPIC_API_KEY hoặc CLAUDE_CODE_OAUTH_TOKEN.";;
  esac
}

# Gỡ Claude Code: binary + (tuỳ chọn) cấu hình/token. Giữ lại dòng PATH .local/bin
# trong .bashrc vì có thể tool khác đang dùng; chỉ gỡ phần auth do lat thêm.
_claude_uninstall() {
  require_root
  ui_yesno "Gỡ Claude Code khỏi VPS này?\n(Xoá binary + phần đăng nhập do lat thêm)" || return 0
  _claude_ensure_path
  "$CLAUDE_BIN" uninstall >/dev/null 2>&1 && info "Đã chạy 'claude uninstall'." || true
  rm -f "$CLAUDE_BIN"
  rm -rf /root/.local/share/claude 2>/dev/null || true
  # gỡ phần auth lat thêm vào .bashrc + xoá env file
  rm -f "$CLAUDE_ENV_FILE"
  sed -i '/# Claude Code auth (lat)/d' "$CLAUDE_BASHRC" 2>/dev/null || true
  sed -i '\#claude-env#d' "$CLAUDE_BASHRC" 2>/dev/null || true
  if ui_yesno "Xoá luôn cấu hình + token đăng nhập của Claude (~/.claude, ~/.config/claude)?"; then
    rm -rf /root/.claude /root/.config/claude 2>/dev/null || true
    ok "Đã xoá cả cấu hình/token đăng nhập."
  fi
  ui_msg "Đã gỡ Claude Code.\n(Mở SSH mới để PATH/biến môi trường cập nhật.)"
}

act_install_claude() {
  require_root

  # Đã cài -> menu cập nhật/cài lại/auth.
  if command -v claude >/dev/null 2>&1 || [ -x "$CLAUDE_BIN" ]; then
    local ver; ver="$("$CLAUDE_BIN" --version 2>/dev/null || claude --version 2>/dev/null)"
    local c
    c="$(ui_menu "Claude Code đã cài (${ver:-?}). Làm gì?" \
      update    "Cập nhật (claude update)" \
      auth      "Đổi/đặt API key hoặc OAuth token" \
      reinstall "Cài lại" \
      uninstall "Gỡ cài đặt Claude Code" \
      back      "Quay lại")" || return 0
    case "$c" in
      update)    _claude_ensure_path; "$CLAUDE_BIN" update 2>&1 | tail -5 || true; ui_msg "Đã chạy cập nhật Claude Code."; return 0;;
      auth)      _claude_auth_prompt; return 0;;
      reinstall) ;;  # rơi xuống phần cài
      uninstall) _claude_uninstall; return 0;;
      *)         return 0;;
    esac
  fi

  need_cmd curl || { ui_msg "Thiếu curl. Chạy: apt-get install -y curl"; return 1; }
  info "Cài Claude Code (native installer)..."
  curl -fsSL https://claude.ai/install.sh | bash || { ui_msg "Cài thất bại - kiểm mạng/quyền."; return 1; }

  _claude_ensure_path
  local ver; ver="$("$CLAUDE_BIN" --version 2>/dev/null)"
  [ -n "$ver" ] && ok "Đã cài Claude Code: ${ver}" || warn "Cài xong nhưng chưa verify được version."

  _claude_auth_prompt

  ui_msg "Claude Code đã cài: ${ver:-?}\n\nDùng (mở SSH MỚI hoặc 'source ~/.bashrc' trước):\n  cd /opt/sites/<id>\n  claude\n\nNếu bỏ qua auth: set ANTHROPIC_API_KEY hoặc CLAUDE_CODE_OAUTH_TOKEN rồi mở shell mới.\nCập nhật: tự động khi chạy (hoặc 'claude update')."
}
