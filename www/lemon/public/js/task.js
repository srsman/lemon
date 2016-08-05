function test(Names){
	var Name;
	for (var i=1;i<5;i++){
		var tempname="mune_x"+i;
		var NewsHot="x"+i;
		if (Names==tempname){
			Nnews=document.getElementById(NewsHot)
			Nnews.style.display='';
		}else{
			Nnews=document.getElementById(NewsHot)
			Nnews.style.display='none';   
		}
	}
}

function deleteTask(id) {
    layer.confirm('亲，确定要删除？', {
        btn: ['是','否']
    }, function(){
        $.get('/task/delete/' + id, function(){
            layer.msg('删除成功!', {icon: 1, time: 1000});
            setTimeout(function() {
                window.location.reload();
            }, 1000);
        });
    }, function(){
        layer.msg('好险么么哒', {icon: 0, time: 1000});
    });
}
