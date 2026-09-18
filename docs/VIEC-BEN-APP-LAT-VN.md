# Việc cần làm bên app.lat.vn (không sửa được trong repo này)

> LATVPS chỉ là **client**. Ba việc dưới đây nằm ở phía server phát hành (`app.lat.vn`,
> repo `/opt/dashboard`) — sửa một mình trong `/opt/latvps` không có tác dụng gì.
>
> Viết ngày **2026-09-19**, sau khi rà soát [issue #1](https://github.com/affiliatecmscom/latvps/issues/1).
> Mọi kết luận bên dưới đều **probe trực tiếp** vào server thật, không suy đoán — lệnh
> kiểm chứng để ở §4 để sau này chạy lại xem đã sửa chưa.

`LICENSE_SERVER` = `https://app.lat.vn/wp-json/acms-license/v1` (khai báo ở `lib/common.sh`).

---

## 1. Endpoint `update/*` phải nhận `license_key` qua POST body

**Hiện trạng:** client buộc phải gửi key trong query string.

```
GET /update/download?plugin=affiliatecms-pro&license_key=ACMS-XXXX-XXXX-XXXX-XXXX&domain=factory
```

Thử POST → server trả **`405 Method Not Allowed`**, tức route đăng ký GET-only.

**Vì sao phải sửa:** license key nằm nguyên văn trong URL → vào access log của app.lat.vn,
log của mọi proxy trung gian, và ảnh chụp màn hình học viên gửi khi nhờ hỗ trợ. **Đúng loại
lỗi đã sửa cho `cron_key` ở commit `585fd3f`** — chỉ khác là lần đó key nằm trên VPS học viên,
lần này nằm ở đầu server.

**Route cần sửa:**

| Route | Ai gọi | Ghi chú |
|---|---|---|
| `/update/download` | `fetch_payload()` trong `lib/common.sh` | |
| `/update/demo/download` | `fetch_demo_bundle()` trong `lib/common.sh` | |
| `/update/check` | **plugin trong TỪNG site học viên** tự gọi | số lượng caller lớn nhất |

`/license-info`, `/activate`, `/deactivate` đã POST sẵn — chỉ nhóm `update/*` còn GET.

**RÀNG BUỘC — đừng flip thẳng sang POST-only.** Mọi bản `lat` cũ và mọi plugin đã cài trên
site học viên vẫn đang gửi GET. Đổi đột ngột = tất cả đứt update cùng lúc. Server phải **nhận
cả hai** một thời gian, rồi mới bỏ GET sau khi số site dùng bản cũ về gần 0.

**Sau khi server xong:** sửa `fetch_payload()` + `fetch_demo_bundle()` dùng
`curl --data-urlencode` (đã có sẵn khuôn mẫu ở `license_check()` cùng file). Khoảng 30 phút.

---

## 2. Server phải công bố hash của file tải về

**Hiện trạng:** response chỉ có `content-type` + `content-length`. Không có `Digest`,
không `ETag`, không header hash nào. Client không có gì để đối chiếu.

**Nói thẳng về giá trị thật.** Issue #1 mô tả việc này là đóng lỗ "nếu license server bị chiếm
hoặc bị MITM". Cần phân biệt rõ:

- Vế **MITM** thì HTTPS đã lo rồi.
- Vế **server bị chiếm** thì **hash lấy từ chính server đó KHÔNG đóng được** — kẻ chiếm server
  sửa file thì sửa luôn hash.

Nên có hai mức, khác hẳn nhau về giá trị:

**Mức rẻ (vài giờ) — nên làm trước.** `/update/check` trả kèm `sha256` của zip; client verify
sau khi tải. Chống được: tải hỏng giữa chừng, server trả trang lỗi HTML thay vì zip, và lệch
phiên bản giữa lớp metadata và lớp lưu trữ file. **Đáng làm, nhưng đừng gọi là chống supply-chain.**

**Mức thật (khó hơn).** Ký zip bằng khoá riêng **offline**, public key ship kèm repo latvps.
Server bị chiếm vẫn không giả được chữ ký vì khoá ký không nằm trên server. Đây mới đúng là
thứ issue #1 mô tả.

**Sau khi server xong:** phía latvps đã có sẵn `verify_sha512()` trong `lib/common.sh` (thêm ở
v3.19.0, có test ở `tests/payload.bats`). Đấu vào `fetch_payload`/`fetch_demo_bundle` khoảng 1 tiếng.
Tham khảo cách làm đã chạy: `lib/actions/setup.sh` verify `wp-cli.phar` bằng file `.sha512`
mà wp-cli publish sẵn.

---

## 3. Endpoint tải theme KHÔNG gate license — cần quyết định

**Hiện trạng:**

```
GET /update/theme/download?slug=affiliateCMS-theme
→ HTTP 200, 1.3 MB application/zip, KHÔNG cần license key
```

Code cũng vậy — trong `fetch_payload()`, plugin thì gửi `license_key=`, riêng theme thì không.
Trong khi `README.md` viết: *"Payload (plugin/theme AffiliateCMS): tải từ app.lat.vn
**gated license**"*. Một trong hai đang sai.

### Mức độ nghiêm trọng: thiệt hại kinh doanh, KHÔNG phải lỗ hổng bảo mật

Đã kiểm tra, ghi lại để khỏi phải lo lại từ đầu:

**Lộ những gì:** chỉ theme. Plugin `affiliatecms-pro` + `affiliatecms-ai` và bundle demo
(DB + uploads + sản phẩm + cấu hình) đều vẫn gated, trả `403` với key sai. Người lấy được theme
chỉ có cái vỏ — theme query thẳng vào bảng `acms_products` (`template-deals.php`,
`taxonomy-acms_reviews_brand.php`) mà bảng đó do plugin tạo, nên cài trần theme lên WordPress
sạch thì phần lớn trang sẽ trống.

**Có tạo lỗ hổng không: không.** Đã soi các entry point công khai của theme:

| Kiểm tra | Kết quả |
|---|---|
| 5 REST route (`inc/rest-api.php`) | `permission_callback => '__return_true'` — công khai, nhưng **mọi arg đều có `sanitize_callback`** (`absint`, `sanitize_email`, `sanitize_textarea_field`, `sanitize_text_field`) |
| 2 AJAX `nopriv` (`inc/brand-ajax.php`) | Có `check_ajax_referer()` (nonce) + `absint`/`sanitize_text_field` |
| Truy vấn SQL | `$wpdb->prepare()` ở mọi chỗ có input người dùng |
| 2 query không `prepare` | `template-deals.php:22`, `inc/template-functions.php:273` — đã đọc tận nơi, **không nhận input nào**, chỉ chuỗi hằng + `$wpdb->prefix` |

**Điểm mấu chốt:** những endpoint đó **vốn đã công khai trên mọi site học viên rồi**, có source
hay không cũng vậy. Ai muốn tấn công chỉ cần gọi `/wp-json/acms/v1/comments` trên một site bất
kỳ, không cần tải theme về. Theme tải được chỉ làm việc *đọc code tìm lỗi* dễ hơn — mà code
đang sạch.

> Phạm vi rà soát: chỉ các entry point công khai của **theme**, không phải audit đầy đủ, và
> **chưa đụng tới 2 plugin** (gated nên ít cấp bách hơn, nhưng codebase lớn hơn nhiều).

### Hai lựa chọn

- **Theme miễn phí là cố ý** → sửa `README.md`, bỏ chữ "gated license" ở dòng mô tả payload.
  1 dòng, không cần đụng server.
- **Muốn gate** → sửa `/update/theme/download` đòi `license_key`, rồi sửa `fetch_payload()`
  gửi kèm key. Việc nhỏ, nhưng **phải làm cả hai đầu cùng lúc** kẻo `lat add` đứt giữa chừng.

---

## 4. Lệnh kiểm chứng lại (chạy bất cứ lúc nào)

```bash
B="https://app.lat.vn/wp-json/acms-license/v1"

# 1. Đã nhận POST chưa?  Hiện: GET 403 (đúng - key sai), POST 405 (chưa nhận POST)
curl -s -o /dev/null -w 'GET  %{http_code}\n' "$B/update/download?plugin=affiliatecms-pro&license_key=ACMS-0000-0000-0000-0000&domain=factory"
curl -s -o /dev/null -w 'POST %{http_code}\n' -X POST -d "plugin=affiliatecms-pro&license_key=ACMS-0000-0000-0000-0000" "$B/update/download"

# 2. Đã có hash chưa?  Hiện: không có header digest/sha nào
curl -sI "$B/update/theme/download?slug=affiliateCMS-theme" | grep -iE 'digest|sha|etag'

# 3. Theme đã gate chưa?  Hiện: HTTP 200 + 1.3MB zip (CHƯA gate)
curl -s -o /dev/null -w 'theme: HTTP %{http_code}  size=%{size_download}\n' "$B/update/theme/download?slug=affiliateCMS-theme"

# 4. Bundle demo + plugin vẫn gated chứ?  Phải là 403
curl -s -o /dev/null -w 'demo:   %{http_code}\n' "$B/update/demo/download?license_key=ACMS-0000-0000-0000-0000"
```

---

## 5. Thứ tự đề xuất

| # | Việc | Ở đâu | Công sức |
|---|---|---|---|
| 1 | Quyết theme có gate hay không | quyết định kinh doanh | 5 phút nghĩ, có thể 0 dòng code |
| 2 | `update/*` nhận POST **song song** GET | app.lat.vn | rồi latvps sửa ~30 phút |
| 3 | `update/check` trả `sha256` | app.lat.vn | rồi latvps sửa ~1 tiếng |
| 4 | (cân nhắc) ký bằng khoá offline | app.lat.vn + latvps | lớn hơn nhiều, quyết sau |

Không việc nào chặn việc nào. Làm được cái nào trước cũng được.

---

## Liên quan

- `docs/ARCHITECTURE.md` §13 — backlog tổng, có trỏ về file này
- `docs/QUY-TRINH-PHAT-HANH.md` §8 — ranh giới giữa `/opt/latvps` và `/opt/dashboard`
- [issue #1](https://github.com/affiliatecmscom/latvps/issues/1) — nguồn gốc
