<?php
require_once __DIR__ . '/../includes/bootstrap.php';
ensure_news_schema();

$articles = [
    [
        'title' => 'Cách chọn giày chạy bộ phù hợp',
        'slug' => 'cach-chon-giay-chay-bo-phu-hop',
        'thumbnail' => 'https://images.unsplash.com/photo-1460353581641-37baddab0fa2?auto=format&fit=crop&w=1200&q=80',
        'excerpt' => 'Hướng dẫn chọn giày chạy bộ theo kiểu bàn chân, cự ly, mặt đường và cảm giác đệm để chạy thoải mái hơn mỗi ngày.',
        'author' => 'ShoeStore Team',
        'tags' => 'Running, Hướng dẫn, Chọn giày',
        'content' => <<<'HTML'
<h2>Vì sao giày chạy bộ cần chọn kỹ?</h2>
<p>Một đôi giày chạy bộ tốt không chỉ đẹp mắt mà còn phải bảo vệ bàn chân trong những chuyển động lặp lại liên tục. Khi chạy, lực tác động lên chân có thể lớn hơn nhiều lần trọng lượng cơ thể, vì vậy phần đệm, độ ổn định, trọng lượng và form giày đều ảnh hưởng trực tiếp đến cảm giác tập luyện. Nếu chọn giày quá chật, gót dễ phồng rộp; nếu đế quá cứng, đầu gối và cổ chân có thể nhanh mỏi; nếu form không hợp bàn chân, bạn sẽ khó duy trì thói quen chạy lâu dài.</p>
<h2>Chọn theo mục tiêu chạy</h2>
<p>Nếu bạn mới bắt đầu chạy nhẹ quanh công viên hoặc máy chạy bộ, hãy ưu tiên mẫu có đệm êm, gót ổn định và thân giày thoáng khí. Người chạy cự ly dài nên chú ý độ bền đế ngoài, độ phản hồi của lớp đệm và trọng lượng vừa phải. Nếu bạn thường chạy nhanh hoặc tập interval, giày nhẹ, ôm chân và có độ nảy tốt sẽ giúp bước chạy linh hoạt hơn. Với đường bê tông hoặc nhựa, đế cần giảm chấn tốt; với đường ẩm hoặc nhiều bụi, độ bám là yếu tố rất đáng cân nhắc.</p>
<h2>Kiểm tra form và size</h2>
<p>Thời điểm thử giày tốt nhất là cuối ngày, khi bàn chân đã giãn tự nhiên. Mũi giày nên còn khoảng nửa đến một đốt ngón tay để tránh đau đầu ngón khi chạy xuống dốc hoặc chạy lâu. Gót phải chắc nhưng không cấn. Nếu chân bè, hãy chọn form rộng hơn hoặc tăng nửa size tùy thương hiệu. Đừng chỉ nhìn size quen thuộc, vì mỗi hãng như Nike, Adidas, New Balance hay Puma có cảm giác ôm chân khác nhau.</p>
<h2>Lời khuyên thực tế</h2>
<p>Khi mua online, hãy đọc kỹ mô tả sản phẩm, xem tồn kho theo size và tham khảo đánh giá của người mua trước. Nếu bạn chạy 3-4 buổi mỗi tuần, nên có ít nhất một đôi chuyên chạy bộ thay vì dùng sneaker lifestyle hằng ngày. Sau khoảng 500-800 km, đệm giày có thể giảm hiệu quả và nên được thay mới. Một đôi giày phù hợp là đôi giúp bạn muốn xỏ chân vào và chạy tiếp, không phải đôi có thông số hào nhoáng nhất.</p>
<h2>Bài viết liên quan</h2>
<p>Bạn có thể đọc thêm bài “Cách chọn size giày online chuẩn” và “Nên chọn Nike, Adidas hay New Balance?” để hiểu rõ hơn về form giày từng thương hiệu.</p>
<p><a class="btn btn-dark" href="products.php?category=running">Mua sắm sản phẩm phù hợp</a></p>
<h2>Đọc thêm từ các nguồn tham khảo</h2>
<ul><li>Nike Running</li><li>Adidas Running</li><li>New Balance Running</li><li>Runner's World</li></ul>
HTML
    ],
    [
        'title' => 'Xu hướng sneaker năm nay',
        'slug' => 'xu-huong-sneaker-nam-nay',
        'thumbnail' => 'https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?auto=format&fit=crop&w=1200&q=80',
        'excerpt' => 'Sneaker năm nay thiên về thiết kế dễ phối, màu trung tính, chất liệu bền và cảm giác mang thoải mái cho cả đi làm lẫn đi chơi.',
        'author' => 'ShoeStore Editorial',
        'tags' => 'Xu hướng, Sneaker, Lifestyle',
        'content' => <<<'HTML'
<h2>Sneaker đang trở nên thực dụng hơn</h2>
<p>Xu hướng sneaker hiện nay không chỉ xoay quanh mẫu mã nổi bật. Người mua quan tâm nhiều hơn đến sự thoải mái, độ bền và khả năng phối đồ trong nhiều hoàn cảnh. Những đôi giày có phom cổ điển, màu trắng, đen, xám, kem, xanh navy hoặc phối màu đơn giản vẫn giữ sức hút vì dễ đi cùng quần jeans, chinos, váy, đồ thể thao hoặc trang phục công sở năng động.</p>
<h2>Phom cổ điển và đế vừa phải</h2>
<p>Chunky sneaker vẫn có chỗ đứng, nhưng kiểu đế quá lớn không còn là lựa chọn duy nhất. Nhiều người quay lại với phom gọn, đế vừa phải, thân giày cân bằng giữa da, da lộn và mesh. Điểm hay của xu hướng này là giày không nhanh lỗi mốt. Một đôi sneaker sạch màu, đường nét rõ và chất liệu dễ vệ sinh có thể dùng nhiều mùa mà vẫn hợp thời.</p>
<h2>Màu sắc dễ ứng dụng</h2>
<p>Màu trắng toàn bộ luôn là lựa chọn an toàn, nhưng năm nay các bản phối xám, nâu nhạt, xanh rêu và đen trắng được ưa chuộng hơn vì ít bám bẩn rõ. Với người thích tạo điểm nhấn, một chi tiết màu ở logo, gót hoặc dây giày là đủ để bộ đồ có cá tính mà không quá rực. Nếu chỉ mua một đôi, hãy chọn màu bạn có thể mang ít nhất ba ngày mỗi tuần.</p>
<h2>Lời khuyên khi mua</h2>
<p>Đừng chạy theo xu hướng nếu đôi giày không hợp form chân. Hãy ưu tiên cảm giác khi đi, chất liệu dễ chăm sóc và size còn đủ. Nếu bạn muốn một đôi đi hằng ngày, nên chọn đế bám tốt và lót êm. Nếu muốn giày chụp ảnh, phối đồ nổi bật, hãy chọn phiên bản có màu nhấn nhưng vẫn giữ nền trung tính. Xu hướng tốt nhất là xu hướng khiến bạn dùng giày thường xuyên, không phải để nằm trong hộp.</p>
<h2>Bài viết liên quan</h2>
<p>Đọc thêm “Cách bảo quản sneaker trắng” nếu bạn thích sneaker sáng màu, hoặc “Cách phân biệt giày chính hãng” trước khi chọn mua các mẫu hot.</p>
<p><a class="btn btn-dark" href="products.php?category=sneaker">Mua sắm sản phẩm phù hợp</a></p>
<h2>Đọc thêm từ các nguồn tham khảo</h2>
<ul><li>Nike</li><li>Adidas Originals</li><li>New Balance Lifestyle</li><li>Highsnobiety</li></ul>
HTML
    ],
    [
        'title' => 'Cách phân biệt giày chính hãng',
        'slug' => 'cach-phan-biet-giay-chinh-hang',
        'thumbnail' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1200&q=80',
        'excerpt' => 'Những dấu hiệu quan trọng giúp bạn kiểm tra tem nhãn, hộp, đường may, chất liệu và nơi bán để tránh mua nhầm giày kém chất lượng.',
        'author' => 'ShoeStore Team',
        'tags' => 'Chính hãng, Mẹo mua hàng, Sneaker',
        'content' => <<<'HTML'
<h2>Bắt đầu từ nơi bán</h2>
<p>Cách đơn giản nhất để giảm rủi ro mua nhầm giày kém chất lượng là chọn nơi bán minh bạch. Một cửa hàng đáng tin thường có thông tin sản phẩm rõ ràng, hình ảnh thật, chính sách đổi trả, hóa đơn và kênh hỗ trợ sau bán. Nếu giá thấp bất thường so với mặt bằng chung, bạn nên kiểm tra kỹ hơn thay vì quyết định chỉ vì khuyến mãi.</p>
<h2>Kiểm tra hộp, tem và mã sản phẩm</h2>
<p>Giày chính hãng thường có hộp in sắc nét, tem rõ thông tin size, mã màu, mã sản phẩm và nơi sản xuất. Mã trên hộp nên khớp với tem bên trong giày. Chữ in trên tem phải đều, không nhòe, không lệch quá nhiều. Một số thương hiệu có QR hoặc mã tra cứu, nhưng không phải mã nào cũng xác minh được công khai, vì vậy hãy xem đây là một yếu tố tham khảo chứ không phải bằng chứng duy nhất.</p>
<h2>Đường may, keo và chất liệu</h2>
<p>Đường may của giày thật thường đều, khoảng cách mũi chỉ ổn định và không có nhiều chỉ thừa. Keo dán có thể xuất hiện nhẹ ở vài vị trí, nhưng không nên lem lớn hoặc có mùi khó chịu. Da, mesh, da lộn và cao su đế thường có cảm giác chắc, màu đồng đều và ít lỗi bề mặt. Với sneaker cao cấp, độ hoàn thiện ở gót, lưỡi gà và logo là nơi dễ phát hiện khác biệt.</p>
<h2>Lời khuyên thực tế</h2>
<p>Khi mua online, hãy yêu cầu ảnh rõ của tem, hộp và các góc giày nếu sản phẩm có giá trị cao. Đọc đánh giá người mua trước, kiểm tra điều kiện đổi trả và giữ lại hóa đơn. Nếu bạn chưa quen phân biệt, hãy chọn cửa hàng có uy tín thay vì tự săn deal không rõ nguồn gốc. Một đôi giày chính hãng không chỉ bền hơn mà còn giúp bạn yên tâm khi sử dụng lâu dài.</p>
<h2>Bài viết liên quan</h2>
<p>Bạn nên đọc thêm “Nên chọn Nike, Adidas hay New Balance?” để hiểu đặc trưng từng thương hiệu trước khi mua.</p>
<p><a class="btn btn-dark" href="products.php">Mua sắm sản phẩm phù hợp</a></p>
<h2>Đọc thêm từ các nguồn tham khảo</h2>
<ul><li>Nike Help</li><li>Adidas Help</li><li>New Balance Support</li><li>Complex Sneakers</li></ul>
HTML
    ],
    [
        'title' => 'Cách bảo quản sneaker trắng',
        'slug' => 'cach-bao-quan-sneaker-trang',
        'thumbnail' => 'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=1200&q=80',
        'excerpt' => 'Các bước vệ sinh, phơi khô và cất giữ sneaker trắng để giày ít ố vàng, giữ form tốt và dùng được lâu hơn.',
        'author' => 'ShoeCare Lab',
        'tags' => 'Bảo quản, Sneaker trắng, Vệ sinh giày',
        'content' => <<<'HTML'
<h2>Vì sao sneaker trắng dễ xuống màu?</h2>
<p>Sneaker trắng đẹp vì sạch và dễ phối đồ, nhưng cũng dễ lộ bụi, vết bẩn và dấu ố vàng. Nguyên nhân thường đến từ bụi đường, nước mưa, mồ hôi, chất tẩy quá mạnh hoặc phơi trực tiếp dưới nắng gắt. Nếu chăm sóc đúng cách, bạn có thể giữ giày trắng sáng lâu hơn mà không cần vệ sinh quá nhiều lần.</p>
<h2>Làm sạch đúng cách</h2>
<p>Trước khi vệ sinh, hãy tháo dây giày và lót nếu có thể. Dùng bàn chải mềm phủi bụi khô trước, sau đó lau bằng khăn ẩm. Với thân da, dùng dung dịch vệ sinh nhẹ và khăn microfiber. Với vải mesh hoặc canvas, chải nhẹ theo vòng tròn, tránh ngâm cả đôi quá lâu. Đế cao su có thể dùng bàn chải cứng hơn một chút, nhưng vẫn nên kiểm soát lực để không làm xước bề mặt.</p>
<h2>Phơi và cất giữ</h2>
<p>Sau khi vệ sinh, nhét giấy trắng hoặc khăn khô vào trong giày để hút ẩm và giữ form. Không dùng giấy báo vì mực có thể lem sang lót hoặc thân giày. Phơi nơi thoáng gió, tránh nắng trực tiếp. Khi cất lâu ngày, nên để giày trong hộp thoáng, có gói hút ẩm và tránh nơi quá nóng. Nếu mang thường xuyên, hãy lau nhanh sau mỗi lần đi mưa hoặc đi đường bụi.</p>
<h2>Lời khuyên thực tế</h2>
<p>Đừng đợi giày quá bẩn mới vệ sinh. Một lần lau nhẹ sau vài lần mang sẽ hiệu quả hơn nhiều so với cố tẩy khi vết bẩn đã bám sâu. Nếu sneaker trắng là đôi đi hằng ngày, bạn có thể dùng xịt bảo vệ chất liệu, nhưng hãy thử ở vùng nhỏ trước. Quan trọng nhất là chọn đúng sản phẩm vệ sinh cho từng chất liệu.</p>
<h2>Bài viết liên quan</h2>
<p>Đọc thêm “Xu hướng sneaker năm nay” để chọn phối màu dễ chăm sóc hơn nếu bạn ngại giày trắng toàn bộ.</p>
<p><a class="btn btn-dark" href="products.php?q=trắng">Mua sắm sản phẩm phù hợp</a></p>
<h2>Đọc thêm từ các nguồn tham khảo</h2>
<ul><li>Nike Care Guide</li><li>Adidas Shoe Care</li><li>Sneaker care community tips</li></ul>
HTML
    ],
    [
        'title' => 'Nên chọn Nike, Adidas hay New Balance?',
        'slug' => 'nen-chon-nike-adidas-hay-new-balance',
        'thumbnail' => 'https://images.unsplash.com/photo-1539185441755-769473a23570?auto=format&fit=crop&w=1200&q=80',
        'excerpt' => 'So sánh nhanh ba thương hiệu phổ biến theo độ êm, phong cách, form chân và nhu cầu sử dụng để bạn chọn đôi phù hợp hơn.',
        'author' => 'ShoeStore Editorial',
        'tags' => 'So sánh, Thương hiệu, Nike, Adidas, New Balance',
        'content' => <<<'HTML'
<h2>Mỗi thương hiệu có thế mạnh riêng</h2>
<p>Nike, Adidas và New Balance đều có lượng người yêu thích lớn, nhưng không nên chọn chỉ vì logo. Nike thường mạnh về thiết kế thể thao, cảm giác năng động và nhiều dòng chạy bộ, bóng rổ, lifestyle. Adidas nổi bật ở sự linh hoạt, các mẫu dễ phối và chất liệu đệm quen thuộc. New Balance được nhiều người đánh giá cao về sự thoải mái, form rộng và phong cách retro hiện đại.</p>
<h2>Khi nào nên chọn Nike?</h2>
<p>Nike phù hợp nếu bạn thích thiết kế khỏe, ôm chân và có nhiều lựa chọn từ chạy bộ đến sneaker đường phố. Một số mẫu Nike có form hơi thon, vì vậy người chân bè nên kiểm tra size kỹ hoặc đọc review trước khi mua. Nếu bạn cần giày tập luyện, hãy ưu tiên dòng có độ bám tốt và đệm ổn định thay vì chỉ chọn theo màu.</p>
<h2>Khi nào nên chọn Adidas?</h2>
<p>Adidas là lựa chọn tốt nếu bạn cần đôi sneaker đi hằng ngày, dễ phối và có cảm giác cân bằng. Nhiều mẫu Adidas hợp cả quần jeans, quần thể thao lẫn trang phục casual đi làm. Với người thích phong cách tối giản, các phối màu trắng, đen, xám hoặc xanh navy của Adidas thường rất dễ dùng.</p>
<h2>Khi nào nên chọn New Balance?</h2>
<p>New Balance thường được yêu thích nhờ form thoải mái, phần đế êm và vẻ ngoài retro. Nếu bạn đi bộ nhiều, đứng lâu hoặc thích giày không quá bó, New Balance là lựa chọn đáng thử. Các phối màu xám, be, navy và xanh rêu cũng dễ phối với phong cách hằng ngày.</p>
<h2>Lời khuyên thực tế</h2>
<p>Hãy bắt đầu từ nhu cầu: chạy bộ, đi làm, đi học, tập luyện hay phối đồ. Sau đó mới chọn thương hiệu. Nếu chân bè, ưu tiên form rộng. Nếu hay đi mưa, chọn chất liệu dễ vệ sinh. Nếu muốn một đôi dùng lâu, chọn màu trung tính và đế bền. Thương hiệu tốt nhất là thương hiệu có đôi giày hợp chân bạn nhất.</p>
<h2>Bài viết liên quan</h2>
<p>Đọc thêm “Cách chọn giày chạy bộ phù hợp” và “Cách chọn size giày online chuẩn” để quyết định tự tin hơn.</p>
<p><a class="btn btn-dark" href="products.php?brand=Nike">Mua sắm sản phẩm phù hợp</a></p>
<h2>Đọc thêm từ các nguồn tham khảo</h2>
<ul><li>Nike</li><li>Adidas</li><li>New Balance</li><li>Runner's World</li></ul>
HTML
    ],
    [
        'title' => 'Cách chọn size giày online chuẩn',
        'slug' => 'cach-chon-size-giay-online-chuan',
        'thumbnail' => 'https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=1200&q=80',
        'excerpt' => 'Hướng dẫn đo chân, đọc bảng size, kiểm tra form giày và chọn size an toàn hơn khi mua giày online.',
        'author' => 'ShoeStore Team',
        'tags' => 'Size guide, Online shopping, Tư vấn',
        'content' => <<<'HTML'
<h2>Đo chân trước khi chọn size</h2>
<p>Khi mua giày online, đo chân là bước quan trọng nhất nhưng thường bị bỏ qua. Hãy đặt bàn chân lên giấy, giữ thẳng người, đánh dấu điểm gót và ngón dài nhất rồi đo chiều dài. Nên đo cả hai chân vì nhiều người có một chân lớn hơn chân còn lại. Lấy số đo của chân lớn hơn để so với bảng size. Nếu bạn thường mang tất dày, hãy đo khi đang mang loại tất đó.</p>
<h2>Đừng chỉ dựa vào size quen thuộc</h2>
<p>Size 40 của thương hiệu này có thể không giống size 40 của thương hiệu khác. Nike có nhiều mẫu ôm hơn, New Balance thường dễ chịu hơn với chân bè, còn Adidas tùy dòng sẽ có cảm giác khác nhau. Vì vậy, hãy đọc mô tả form giày và đánh giá người mua. Nếu sản phẩm có ghi “form nhỏ”, cân nhắc tăng nửa size. Nếu có ghi “true to size”, bạn có thể chọn size thường mang.</p>
<h2>Kiểm tra chiều rộng bàn chân</h2>
<p>Chiều dài không phải yếu tố duy nhất. Người chân bè cần chú ý phần mũi và thân giày. Nếu chọn giày quá ôm, bàn chân sẽ bị ép ngang, dễ đau sau vài giờ sử dụng. Với giày chạy bộ, nên có khoảng trống nhẹ ở mũi để ngón chân cử động. Với sneaker lifestyle, cảm giác ôm vừa phải ở gót và mu bàn chân sẽ giúp đi chắc hơn.</p>
<h2>Lời khuyên thực tế</h2>
<p>Nếu bạn phân vân giữa hai size, hãy xem mục đích sử dụng. Đi chạy hoặc đi bộ lâu thường cần dư nhẹ ở mũi. Đi phối đồ hằng ngày có thể chọn vừa hơn, nhưng không nên chật. Trước khi thanh toán, hãy kiểm tra tồn kho đúng size, chính sách đổi trả và đánh giá về form. Một thao tác đo chân mất vài phút có thể giúp bạn tránh đổi size nhiều lần.</p>
<h2>Bài viết liên quan</h2>
<p>Bạn có thể đọc thêm “Cách chọn giày chạy bộ phù hợp” nếu đang chọn giày luyện tập, hoặc “Xu hướng sneaker năm nay” nếu cần chọn sneaker đi hằng ngày.</p>
<p><a class="btn btn-dark" href="products.php">Mua sắm sản phẩm phù hợp</a></p>
<h2>Đọc thêm từ các nguồn tham khảo</h2>
<ul><li>Nike Size Guide</li><li>Adidas Size Chart</li><li>New Balance Fit Guide</li><li>Runner's World</li></ul>
HTML
    ],
];

$commonExtra = <<<'HTML'
<h2>Checklist nhanh trước khi quyết định</h2>
<p>Trước khi thêm sản phẩm vào giỏ hàng, hãy kiểm tra ba điểm: mục đích sử dụng, size còn tồn và cảm giác form giày được mô tả trong phần thông tin sản phẩm. Nếu bạn mua để đi hằng ngày, độ thoải mái và độ bền nên được ưu tiên hơn màu sắc quá nổi bật. Nếu bạn mua để tập luyện, hãy chú ý đế ngoài, trọng lượng, độ ôm gót và khả năng thoáng khí. Một quyết định mua tốt thường đến từ việc cân bằng nhu cầu thật, ngân sách và thói quen sử dụng chứ không chỉ từ tên thương hiệu.</p>
<h2>Những lỗi thường gặp khi mua giày online</h2>
<p>Nhiều người chọn size theo thói quen mà không đọc mô tả form, bỏ qua tồn kho từng size hoặc không kiểm tra chính sách đổi trả. Một số người chỉ nhìn ảnh đại diện mà quên xem chất liệu, chiều cao đế và mục đích thiết kế của sản phẩm. Khi mua online, bạn nên đọc kỹ mô tả, xem đánh giá, so sánh giá sau coupon và tính cả phí vận chuyển, VAT để biết tổng thanh toán thực tế. Nếu còn phân vân, hãy lưu lại vài mẫu tương tự rồi so sánh theo tiêu chí cụ thể.</p>
<h2>Cách áp dụng tại ShoeStore</h2>
<p>Tại ShoeStore, bạn có thể tìm sản phẩm theo danh mục, thương hiệu, khoảng giá và từ khóa. Trang chi tiết sản phẩm hiển thị size, tồn kho, giá bán, đánh giá và nút thêm vào giỏ hàng. Khi vào giỏ, hệ thống cho phép chọn một hoặc nhiều sản phẩm, áp dụng coupon và xem tổng tiền sau giảm trước khi thanh toán. Sau khi đặt hàng, bạn có thể theo dõi trạng thái đơn, xuất hóa đơn PDF/Excel và gửi đánh giá khi đơn đã hoàn tất. Quy trình này giúp việc mua giày online rõ ràng hơn, đặc biệt khi bạn cần kiểm soát size, giá và thời gian xử lý đơn.</p>
HTML;

db()->exec("ALTER TABLE news CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$stmt = db()->prepare('UPDATE news SET title=?, thumbnail=?, excerpt=?, content=?, author=?, tags=?, status="published", updated_at=NOW() WHERE slug=?');
$insert = db()->prepare('INSERT INTO news(title,slug,thumbnail,excerpt,content,author,tags,status,created_at) VALUES(?,?,?,?,?,?,?,"published",NOW())');
foreach ($articles as $article) {
    $content = $article['content'] . $commonExtra;
    $stmt->execute([$article['title'], $article['thumbnail'], $article['excerpt'], $content, $article['author'], $article['tags'], $article['slug']]);
    if ($stmt->rowCount() === 0) {
        $insert->execute([$article['title'], $article['slug'], $article['thumbnail'], $article['excerpt'], $content, $article['author'], $article['tags']]);
    }
}

echo "Seeded " . count($articles) . " long UTF-8 news articles.\n";
