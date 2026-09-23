# Font dùng để dựng ảnh

`Montserrat-ExtraBold.ttf`, `Montserrat-SemiBold.ttf` (giấy phép SIL OFL 1.1, xem `OFL.txt`),
lấy từ kho gốc của font: https://github.com/JulietaUla/Montserrat

Vì sao cần file TTF trong repo khi site đã nạp Montserrat qua Google Fonts: web font chỉ dùng
được cho TRÌNH DUYỆT. Ảnh OG của trang store dựng ở phía máy chủ bằng GD, mà GD cần file TTF
thật. Kho `google/fonts` chỉ còn bản variable (`Montserrat[wght].ttf`), GD đọc bản đó ra đúng
nét Regular chứ không ra ExtraBold, nên phải lấy bản static ở kho gốc.

Dùng ở: `scripts/bestproducts-gen-store-og.php`.
