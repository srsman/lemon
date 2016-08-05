function show(id) {
    layer.open({
        type: 2,
        title: '订单编辑',
        shadeClose: true,
        shade: false,
        area: ['735px', '475px'],
        content: ['/order/edit/' + id, 'no']
    }); 
}
