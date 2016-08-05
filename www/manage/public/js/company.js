function deleteCompany(id) {
    layer.confirm('亲，确定要删除？', {
        btn: ['是','否']
    }, function(){
        var url = '/company/delete/' + id;
        $.get(url, function(){
            layer.msg('删除成功!', {icon: 1, time: 1000});
            setTimeout(function() {
                window.location.reload();
            }, 1000);
        });
    }, function(){
        layer.msg('好险么么哒', {icon: 0, time: 1000});
    });
}
