#!/usr/bin/env bash
# actions/self_update.sh - tự cập nhật bộ lệnh lat.
# Ưu tiên git pull (nếu /opt/latvps là git repo); fallback tải tarball.
# GIỮ nguyên: .license, proxy/.env, payload/, /opt/sites (đều ngoài git / gitignore).

# Nguồn tarball khi KHÔNG phải git. Mặc định lấy tarball của chính repo GitHub
# (cdn.lat.vn/latvps.tar.gz trước đây chưa bao giờ tồn tại -> nhánh fallback này luôn 404).
LATVPS_TARBALL_URL="${LATVPS_TARBALL_URL:-https://github.com/affiliatecmscom/latvps/archive/refs/heads/main.tar.gz}"

_current_version() { cat "${WPF_ROOT}/VERSION" 2>/dev/null || echo "0.0.0"; }

# Kiểm tra cú pháp mọi script bash trong cây cho trước. Trả 0 nếu sạch.
_syntax_ok() {
  local root="$1" f
  while IFS= read -r f; do
    bash -n "$f" 2>/dev/null || { warn "Lỗi cú pháp: $f"; return 1; }
  done < <(find "$root" \( -name '*.sh' -o -path '*/bin/lat' \) -type f)
  return 0
}

act_self_update() {
  require_root
  info "Phiên bản hiện tại: $(_current_version)"

  if [ -d "${WPF_ROOT}/.git" ] && need_cmd git; then
    # Bản đã GHIM (detached HEAD do LATVPS_REF=vX.Y.Z) thì không tự kéo đi - báo rõ cách gỡ ghim,
    # thay vì để git pull fail rồi in "git pull thất bại" khó hiểu.
    if ! git -C "$WPF_ROOT" symbolic-ref -q HEAD >/dev/null 2>&1; then
      local pinned; pinned="$(git -C "$WPF_ROOT" describe --tags --always 2>/dev/null || echo '?')"
      ui_msg "Bản lat đang GHIM ở '${pinned}' nên không tự cập nhật (cố ý).\n\nGỡ ghim để theo bản mới nhất:\n  git -C ${WPF_ROOT} checkout main && lat update"
      return 0
    fi

    # Ghi lại commit hiện tại TRƯỚC khi pull -> có mốc để rollback.
    local prev; prev="$(git -C "$WPF_ROOT" rev-parse HEAD 2>/dev/null || true)"
    info "Cập nhật qua git..."
    if git -C "$WPF_ROOT" pull --ff-only >/dev/null 2>&1; then
      # TỰ ĐỘNG rollback khi bản mới lỗi cú pháp. Bản cũ chỉ warn "cân nhắc rollback bằng git"
      # rồi return -> code hỏng ĐÃ nằm trên đĩa, lần chạy `lat` kế tiếp là chết, và học viên
      # không biết gõ lệnh git nào để lùi lại.
      if ! _syntax_ok "$WPF_ROOT"; then
        warn "Bản mới có LỖI CÚ PHÁP - tự động quay về bản cũ..."
        if [ -n "$prev" ] && git -C "$WPF_ROOT" reset --hard "$prev" >/dev/null 2>&1; then
          ui_msg "Đã rollback về ${prev:0:7} - lat vẫn chạy bình thường, không mất gì.\n\nVui lòng báo lỗi này cho nhóm phát triển."
        else
          warn "Rollback THẤT BẠI. Chạy tay: git -C ${WPF_ROOT} reset --hard ${prev:-HEAD~1}"
        fi
        return 1
      fi
      chmod +x "${WPF_ROOT}/bin/lat" 2>/dev/null || true
      ln -sf "${WPF_ROOT}/bin/lat" /usr/local/bin/lat
      # Cài/làm mới profile.d (bản mới có thể đổi script) + cập nhật cache để tắt thông báo.
      [ -f "${WPF_ROOT}/assets/profile-latvps-update.sh" ] \
        && install -m 0644 "${WPF_ROOT}/assets/profile-latvps-update.sh" /etc/profile.d/latvps-update.sh 2>/dev/null || true
      act_update_check --refresh >/dev/null 2>&1 || true
      ui_msg "Đã cập nhật lat qua git.\nPhiên bản: $(_current_version)\nChạy lại 'lat' để dùng bản mới."
      return 0
    fi
    warn "git pull thất bại."; return 1
  fi

  # Fallback tarball
  info "Tải bản mới từ ${LATVPS_TARBALL_URL}..."
  local tmp; tmp="$(mktemp -d)"
  if ! curl -fsSL "$LATVPS_TARBALL_URL" -o "${tmp}/latvps.tar.gz"; then
    warn "Tải tarball thất bại."; rm -rf "$tmp"; return 1
  fi
  tar -C "$tmp" -xzf "${tmp}/latvps.tar.gz" || { warn "Giải nén lỗi."; rm -rf "$tmp"; return 1; }
  # tarball giả định giải nén ra thư mục latvps/
  local newroot; newroot="$(find "$tmp" -maxdepth 2 -name VERSION -printf '%h\n' | head -1)"
  [ -n "$newroot" ] || newroot="${tmp}/latvps"
  _syntax_ok "$newroot" || { warn "Bản mới lỗi cú pháp - huỷ."; rm -rf "$tmp"; return 1; }

  info "Áp bản mới (giữ license/sites/payload)..."
  rsync -a --delete \
    --exclude '.license' --exclude 'proxy/.env' \
    --exclude 'payload/' --exclude 'bin/wp-cli.phar' \
    "${newroot}/" "${WPF_ROOT}/"
  chmod +x "${WPF_ROOT}/bin/lat" 2>/dev/null || true
  ln -sf "${WPF_ROOT}/bin/lat" /usr/local/bin/lat
  rm -rf "$tmp"
  ui_msg "Đã cập nhật lat.\nPhiên bản: $(_current_version)\nChạy lại 'lat'."
}
