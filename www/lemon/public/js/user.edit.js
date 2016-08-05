var app = new Vue({
    el: '#app',
    data: {
        uid: '',
        icon: '',
        type: '',
        name: '',
        password: '',
        callerid: '',
        phone: '',
        calls: '',
        web: ''
    },
    ready() {
            this.uid = $('input[name="uid"]').val();
            this.icon = $('select[name="icon"]').val();
            this.type = $('select[name="type"]').val();
            this.name =  $('input[name="name"]').val();
            this.password = $('input[name="password"]').val();
            this.callerid = $('input[name="callerid"]').val();
            this.phone = $('input[name="phone"]').val();
            this.calls = $('input[name="calls"]').is(":checked");
            this.web = $('input[name="web"]').is(":checked");
    },
    methods: {
        save: function () {
            var data = new Object();

            if (this.icon != $('select[name="icon"]').val()) {
                data.icon = $('select[name="icon"]').val();
            }
            
            if (this.type != $('select[name="type"]').val()) {
                data.type = $('select[name="type"]').val();
            }
            
            if (this.name != $('input[name="name"]').val()) {
                data.name = $('input[name="name"]').val();
            }
            
            if (this.password != $('input[name="password"]').val()) {
                data.password = $('input[name="password"]').val();
            }
            
            if (this.callerid != $('input[name="callerid"]').val()) {
                data.callerid = $('input[name="callerid"]').val();
            }

            if (this.phone != $('input[name="phone"]').val()) {
                data.phone = $('input[name="phone"]').val();
            }
            
            if (this.calls != $('input[name="calls"]').is(":checked")) {
                data.calls = $('input[name="calls"]').is(":checked");
            }
            
            if (this.web != $('input[name="web"]').is(":checked")) {
                data.web = $('input[name="web"]').is(":checked");
            }
            

            var url = '/api/user_update/' + this.uid;
            this.$http.put(url, data).then((response) => {
                var res = response.json();
                if (res.status == 'ok') {
                    window.location.href = 'http://' + window.location.host + '/user';
                } else {
                    layer.alert(res.message, {title: '提示信息', icon: 2});
                }
            }, (response) => {
                layer.alert('抱歉，与服务器通信发生错误', {title: '提示信息', icon: 2});
            });

            console.log(data);
        }
    }
 });
