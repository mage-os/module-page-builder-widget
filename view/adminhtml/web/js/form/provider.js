define([
    'jquery',
    'underscore',
    'Magento_PageBuilder/js/form/provider',
    'uiRegistry'
], function ($, _, Provider, registry) {
    'use strict';

    return Provider.extend({
        save: function (options) {
            let save = this._super.bind(this, options),
                widgetBuilders = this.getWidgetBuilders();

            if (_.isEmpty(widgetBuilders)) {
                save();

                return this;
            }

            $.when.apply($, widgetBuilders.map(function (widgetBuilder) {
                return widgetBuilder.prepareForSave();
            })).done(function () {
                save();
            });

            return this;
        },

        getWidgetBuilders: function () {
            return registry.filter(function (component) {
                return component &&
                    component.ns === this.ns &&
                    typeof component.prepareForSave === 'function';
            }.bind(this));
        }
    });
});
