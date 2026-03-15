// 全局水印初始化函数
function initWatermark() {
    // 获取当前用户信息
    $.ajax({
        url: 'api/index.php?route=auth/current_user',
        method: 'GET',
        success: function(response) {
            if (response.success && response.data) {
                const watermarkText = `${response.data.real_name}(${response.data.username})`;
                const grid = document.getElementById('watermarkGrid');
                
                // 清空现有水印
                if (grid) {
                    grid.innerHTML = '';
                    
                    // 生成新的水印
                    for (let i = 0; i < 36; i++) {
                        const div = document.createElement('div');
                        div.className = 'watermark-item';
                        const span = document.createElement('span');
                        span.className = 'watermark-text';
                        span.innerHTML = `${watermarkText}<br/>${new Date().toLocaleString()}`;
                        div.appendChild(span);
                        grid.appendChild(div);
                    }
                }
            }
        },
        error: function(error) {
            console.error('获取用户信息失败:', error);
        }
    });
}

// 定期更新水印
function updateWatermark() {
    initWatermark();
}

// 页面加载完成后初始化水印
$(document).ready(function() {
    initWatermark();
    
    // 每分钟更新一次水印（更新时间戳）
    setInterval(updateWatermark, 60000);
    
    // 窗口大小改变时重新生成水印
    $(window).on('resize', initWatermark);
}); 