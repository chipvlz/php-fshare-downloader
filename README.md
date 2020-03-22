# php-fshare-downloader
Script có thể download tất cả các file theo Folder hoặc từng File riêng lẻ trên FShare viết bằng PHP. Nên sử dụng PHP7 là tối thiểu.

# 1. Install Packages

Sau khi clone về thì mở terminal gõ lệnh: ```composer install```

# 2. Chỉ định folder hoặc file cần tải
Chỉ định ID của Folder hoặc ID của file theo dạng array vào biến ```$link```. Nếu muốn skip file nhất định thì chỉ định ID vào mục skip

# 3. Chỉ định tài khoản FShare

Mở file index.php cập nhật tài khoản fshare của mình vào ```$fshareAccount```, tham số type để là ```free``` hoặc ```premium``` tùy vào loại tài khoản bạn đang sử dụng

# 4. Chạy script, ưu tiên nên chạy qua terminal hoặc cắm trên vps
Vào terminal gõ: ```php index.php``` và chờ kết quả
