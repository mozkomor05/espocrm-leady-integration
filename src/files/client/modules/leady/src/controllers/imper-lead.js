define('leady:controllers/imper-lead', 'controllers/record', function (Dep) {

    return Dep.extend({

        actionConvert: function (id) {
            this.main('leady:views/imper-lead/convert', {
                id: id
            });
        },

    });
});
