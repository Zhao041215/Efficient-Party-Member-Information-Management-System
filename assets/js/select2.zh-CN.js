/*! Local Select2 zh-CN translations */
(function (factory) {
    if (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.select2 && jQuery.fn.select2.amd) {
        factory(jQuery.fn.select2.amd);
    }
}(function (amd) {
    amd.define('select2/i18n/zh-CN', [], function () {
        return {
            errorLoading: function () {
                return '无法载入结果。';
            },
            inputTooLong: function (args) {
                var over = args.input.length - args.maximum;
                return '请删除' + over + '个字符';
            },
            inputTooShort: function (args) {
                var remaining = args.minimum - args.input.length;
                return '请再输入至少' + remaining + '个字符';
            },
            loadingMore: function () {
                return '载入更多结果...';
            },
            maximumSelected: function (args) {
                return '最多只能选择' + args.maximum + '个项目';
            },
            noResults: function () {
                return '未找到结果';
            },
            searching: function () {
                return '搜索中...';
            },
            removeAllItems: function () {
                return '删除所有项目';
            }
        };
    });
}));
