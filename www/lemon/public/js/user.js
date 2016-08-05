function changeStatus(uid, opt) {

    var vm = new Vue({
        el: '#user-' + uid,
        compiled() {
            var data = {"status": opt};
            var url = '/api/user_update/' + uid;
            this.$http.put(url, data).then((response) => {
                var res = response.json();
                if (res.status == 'ok') {
                    setTimeout("showStatus('" + uid + "')", 500);
                } else {
                    layer.alert(res.message, {title: '提示信息', icon: 2});
                }
            }, (response) => {
                layer.alert('抱歉，与服务器通信发生错误', {title: '提示信息', icon: 2});
            });
        }
    });
}

function showStatus(uid) {

    var vm = new Vue({
        el: '#user-' + uid,
        data: {
            uuid: uid,
            name: uid,
            icon: '001.jpg',
            type: '普通座席',
            status: '启 用',
            statusValue: 0,
            statusStyle: ' label-success',
            lastLogin: '1970-01-01 08:00:00',
            lastIpAddr: '0.0.0.0',
            btnStyle:  ' glyphicon-edit',
            btnText: '启 用'
        },
        replace: false,
        template: '<td class="td-icon"><img src="/img/{{ icon }}.jpg" class="img-circle icon" alt="Responsive image"></td>' +
            '<td class="td-account text-center"><b>{{ uuid }}</b></td>' +
            '<td class="text-primary">{{ name }}</td>' +
            '<td>{{ type }}</td>' +
            '<td><span class="label{{ statusStyle }}">{{ status }}</span></td>' +
            '<td>{{ lastLogin }}</td>' +
            '<td>{{ lastIpAddr}}</td>' +
            '<td><a class="btn btn-default btn-xs" href="javascript:;" onClick="changeStatus(' + "'{{ uuid }}'"+ ', {{statusValue}})"><span class="glyphicon{{ btnStyle }}"></span> {{ btnText }}</a></td>' +
            '<td class="text-center td-edit"><a class="btn btn-default btn-xs" href="/user/edit/{{ uuid }}"><span class="glyphicon glyphicon-edit"></span> 编 辑</a></td>',
        compiled() {
            var data = 'uid,name,icon,type,status,last_login,last_ipaddr';
            url = '/api/user_get/' + uid + '?attr=' + data;
            this.$http.options.emulateHTTP = true;
            this.$http.get(url).then((response) => {
                var ret = response.json();
                if (ret.status == 'ok') {
                    this.uuid = ret.data.uid;
                    this.name = ret.data.name;
                    this.icon = ret.data.icon;

                    if (ret.data.type == 2) {
                        this.type = '质检座席';
                    } else if (ret.data.type == 3) {
                        this.type = '普通座席';
                    } else {
                        this.type = '未知类型';
                    }
                    
                    if (ret.data.status == '0') {
                        this.status = '禁 用';
                        this.statusValue = 1;
                        this.statusStyle = ' label-danger';
                        this.btnStyle = ' glyphicon-ok-circle';
                        this.btnText = '启 用';
                    } else {
                        this.status = '启 用';
                        this.statusValue = 0;
                        this.statusStyle = ' label-success';
                        this.btnStyle = ' glyphicon-unchecked';
                        this.btnText = '禁 用';
                    }

                    this.lastLogin = ret.data.last_login;
                    this.lastIpAddr = ret.data.last_ipaddr;
                } else {
                    layer.msg(ret.message, {icon: 2});
                }
            }, (response) => {
                layer.msg('抱歉，与服务器通信发生错误', {icon: 2});
            });
        }
    });
}
