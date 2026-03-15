// 添加全局AJAX错误处理
$.ajaxSetup({
    error: function(xhr, status, error) {
        if (xhr.status === 401) {
            const response = xhr.responseJSON || {};
            // 登录失效，显示提示并跳转
            Swal.fire({
                title: '登录已失效',
                text: response.message || '请重新登录',
                icon: 'warning',
                confirmButtonText: '确定',
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // 清除本地存储的任何用户相关数据
                    localStorage.clear();
                    sessionStorage.clear();
                    // 获取当前URL中的module参数
                    const urlParams = new URLSearchParams(window.location.search);
                    const module = urlParams.get('module');
                    // 跳转到登录页，并保留module参数
                    window.location.href = module ? `login.php?module=${module}` : 'login.php';
                }
            });
        }
    },
    // 添加通用的请求拦截器
    beforeSend: function(xhr) {
        // 添加时间戳防止缓存
        this.url += (this.url.indexOf('?') === -1 ? '?' : '&') + '_t=' + new Date().getTime();
    }
});