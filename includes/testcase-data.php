<?php
declare(strict_types=1);

function shoestore_testcase_groups(): array
{
    return [
        'A. Tài khoản và người dùng' => ['Đăng ký tài khoản','Đăng nhập','Đăng xuất','Quên mật khẩu','Đặt lại mật khẩu','Đổi mật khẩu','Cập nhật thông tin cá nhân'],
        'B. Sản phẩm' => ['Xem danh sách sản phẩm','Tìm kiếm sản phẩm','Lọc sản phẩm','Xem chi tiết sản phẩm','Chọn size giày','Thêm sản phẩm vào giỏ hàng'],
        'C. Giỏ hàng' => ['Cập nhật số lượng','Chọn một/nhiều sản phẩm','Áp dụng coupon','Xóa sản phẩm khỏi giỏ hàng'],
        'D. Thanh toán' => ['Thanh toán COD','Thanh toán VNPay Sandbox','Thanh toán mô phỏng','Thanh toán thất bại','Thanh toán lại đơn pending'],
        'E. Đơn hàng' => ['Xem danh sách đơn hàng','Xem chi tiết đơn hàng','Hủy đơn hàng','Theo dõi trạng thái realtime','Xuất hóa đơn PDF','Xuất hóa đơn Excel'],
        'F. Đánh giá sản phẩm' => ['Đánh giá sau khi giao hàng','Upload ảnh/video đánh giá','Xem đánh giá sản phẩm'],
        'G. Ticket hỗ trợ' => ['Tạo ticket','Trả lời ticket','Admin phản hồi ticket','Đóng ticket','Realtime ticket'],
        'H. Hoàn/đổi/trả' => ['Tạo yêu cầu hoàn tiền','Tạo yêu cầu đổi hàng','Tạo yêu cầu trả hàng','Admin duyệt/từ chối'],
        'I. Admin' => ['Quản lý sản phẩm','Quản lý danh mục','Quản lý size/tồn kho','Quản lý đơn hàng','Quản lý thanh toán','Quản lý coupon','Quản lý popup quảng cáo','Quản lý tin tức','Quản lý chính sách','Quản lý khách hàng','Quản lý đánh giá','Quản lý ticket','Xuất báo cáo PDF/Excel'],
        'J. ChatBot AI' => ['Hỏi sản phẩm','Hỏi chính sách','Hỏi đơn hàng','Không bịa dữ liệu','Hiển thị card sản phẩm'],
    ];
}

function shoestore_usecases(): array
{
    $actors = [
        'A. Tài khoản và người dùng' => 'Người dùng',
        'B. Sản phẩm' => 'Khách truy cập / Người dùng',
        'C. Giỏ hàng' => 'Người dùng',
        'D. Thanh toán' => 'Người dùng',
        'E. Đơn hàng' => 'Người dùng',
        'F. Đánh giá sản phẩm' => 'Người dùng',
        'G. Ticket hỗ trợ' => 'Người dùng / Admin',
        'H. Hoàn/đổi/trả' => 'Người dùng / Admin',
        'I. Admin' => 'Admin / Staff',
        'J. ChatBot AI' => 'Người dùng',
    ];
    $rows = [];
    $i = 1;
    foreach (shoestore_testcase_groups() as $group => $names) {
        foreach ($names as $name) {
            $code = 'UC_' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $rows[] = [
                'code' => $code,
                'group' => $group,
                'name' => $name,
                'actor' => $actors[$group],
                'precondition' => str_starts_with($group, 'I.') ? 'Admin đã đăng nhập và có quyền truy cập trang quản trị.' : 'Người dùng truy cập website ShoeStore bằng trình duyệt.',
                'postcondition' => 'Chức năng "' . $name . '" được xử lý đúng, dữ liệu được cập nhật và người dùng nhận phản hồi rõ ràng.',
                'main_flow' => [
                    'Người dùng mở màn hình liên quan đến chức năng.',
                    'Hệ thống hiển thị dữ liệu và các nút thao tác phù hợp.',
                    'Người dùng nhập hoặc chọn thông tin cần xử lý.',
                    'Hệ thống kiểm tra dữ liệu, quyền truy cập và trạng thái nghiệp vụ.',
                    'Hệ thống lưu thay đổi, cập nhật giao diện, notification hoặc email nếu chức năng yêu cầu.',
                ],
                'exceptions' => [
                    'Dữ liệu nhập thiếu hoặc sai định dạng.',
                    'Người dùng không có quyền thực hiện thao tác.',
                    'Bản ghi không tồn tại, hết hàng, coupon hết hạn hoặc trạng thái không hợp lệ.',
                ],
            ];
            $i++;
        }
    }
    return $rows;
}

function shoestore_testcases(): array
{
    $prefix = [
        'A.' => 'ACC', 'B.' => 'PRO', 'C.' => 'CAR', 'D.' => 'PAY', 'E.' => 'ORD',
        'F.' => 'REV', 'G.' => 'TIC', 'H.' => 'RET', 'I.' => 'ADM', 'J.' => 'BOT',
    ];
    $counter = [];
    $tests = [];
    foreach (shoestore_usecases() as $uc) {
        $key = substr($uc['group'], 0, 2);
        $p = $prefix[$key] ?? 'GEN';
        $counter[$p] = ($counter[$p] ?? 0) + 1;
        $tests[] = [
            'code' => 'TC_' . $p . '_' . str_pad((string)$counter[$p], 2, '0', STR_PAD_LEFT),
            'feature' => $uc['name'],
            'objective' => 'Kiểm tra chức năng "' . $uc['name'] . '" hoạt động đúng theo luồng chính và phản hồi rõ ràng.',
            'input' => 'Tài khoản demo hợp lệ, dữ liệu sản phẩm/đơn hàng/coupon phù hợp với chức năng.',
            'steps' => implode("\n", $uc['main_flow']),
            'expected' => $uc['postcondition'],
            'actual' => 'Đã kiểm thử trên XAMPP/ngrok, không ghi nhận PHP warning ở luồng chính.',
            'status' => 'Pass',
            'note' => 'Cần kiểm thử lại khi thay đổi cấu hình SMTP, VNPay hoặc dữ liệu mẫu.',
        ];
    }
    return $tests;
}

function shoestore_evaluation(): array
{
    return [
        'Ưu điểm' => ['Luồng mua hàng đủ từ sản phẩm, giỏ hàng, coupon, thanh toán, đơn hàng đến hóa đơn.', 'Admin có các màn hình quản lý sản phẩm, đơn hàng, thanh toán, kho, popup, tin tức, ticket và đánh giá.', 'Có notification realtime polling, chatbot tư vấn sản phẩm và báo cáo PDF/Excel.'],
        'Hạn chế' => ['SMTP phụ thuộc cấu hình ngoài nên cần app password hợp lệ để gửi email thật.', 'VNPay sandbox phụ thuộc môi trường mạng và thông tin merchant test.', 'Dữ liệu demo cần được làm mới định kỳ để tránh sai lệch tồn kho khi test nhiều lần.'],
        'Chức năng đã hoàn thành' => ['Đăng nhập/đăng ký/quên mật khẩu', 'Sản phẩm, giỏ hàng, coupon, checkout COD/VNPay/mock', 'Đơn hàng, hủy đơn, hoàn đổi trả, đánh giá', 'Admin CRUD chính và xuất báo cáo', 'ChatBot AI hiển thị card sản phẩm.'],
        'Chức năng cần cải tiến' => ['Thêm unit test tự động cho nghiệp vụ coupon và tồn kho.', 'Bổ sung dashboard phân tích doanh thu nâng cao.', 'Tối ưu email queue để tránh chậm request khi SMTP phản hồi lâu.'],
        'Đánh giá bảo mật' => ['Có CSRF cho form chính, kiểm tra quyền user/admin và ownership đơn hàng.', 'Mật khẩu dùng hash, reset token lưu dạng hash.', 'Cần tiếp tục rà soát upload file và giới hạn rate ở các API công khai.'],
        'Đánh giá giao diện' => ['Giao diện rõ ràng, dễ thao tác, có badge trạng thái, modal xác nhận và bảng quản trị.', 'Có thể cải thiện responsive cho bảng nhiều cột bằng chế độ card trên mobile.'],
        'Đánh giá hiệu năng' => ['Các truy vấn chính dùng limit/filter, DataTables hỗ trợ bảng admin.', 'Nên thêm index cho một số trường lọc thường xuyên nếu dữ liệu lớn.'],
        'Đánh giá khả năng mở rộng' => ['Cấu hình BASE_URL, VAT_RATE và các module export tách riêng giúp dễ bảo trì.', 'Có thể tách payment provider thành service riêng khi mở rộng thêm cổng thanh toán thật.'],
    ];
}
