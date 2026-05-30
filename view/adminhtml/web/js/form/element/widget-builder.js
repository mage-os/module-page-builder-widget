define([
    'jquery',
    'underscore',
    'ko',
    'Magento_Ui/js/form/element/abstract',
    'Magento_PageBuilder/js/config',
    'mage/translate',
    'mage/utils/objects',
    'uiRegistry'
], function ($, _, ko, Abstract, Config, $t, objectUtils, registry) {
    'use strict';

    return Abstract.extend({
        chooserOptions: [{
            'type': '',
            'code': '',
            'name': $t('-- Please Select --'),
            'description': ''
        }],
        preview: null,
        errorMessage: null,
        selectedWidgetCode: '',
        saveIsAvailable: false,
        selectedWidgetDescription: '',
        widgetHtmlForm: null,
        savedWidgetHtmlForm: null,
        widgetData: {},
        messages: {
            UNKOWN_ERROR: $t('Sorry, there was an error getting requested content. ' +
                'Please contact the store owner.'),
            UNKNOWN_SELECTION: $t('The currently selected widget does not exist.')
        },
        defaults: {
            elementTmpl: 'MageOS_PageBuilderWidget/form/element/widget-builder',
        },
        requestData: {
            method: 'POST',
            data: {
                'form_key': window.FORM_KEY
            }
        },

        /**
         * Init observable on widget content
         *
         * @returns {exports}
         */
        initObservable: function () {
            let self = this;
            this._super();
            this.observe('chooserOptions selectedWidgetCode saveIsAvailable '+
                'selectedWidgetDescription errorMessage widgetHtmlForm widgetData');
            this.selectedWidgetCode.subscribe(function(code) {
                self.initWidget(code);
            }, this);
            return this;
        },

        updateValue: function () {
            this.prepareForSave().fail(function () {});
        },

        prepareForSave: function () {
            let $form = $('#widgetForm' + this.uid);

            if (!this.saveIsAvailable() || !$form.length || this.widgetHtmlForm() === null) {
                return $.Deferred().resolve().promise();
            }

            return this.submitWidgetForm($form);
        },

        submitWidgetForm: function ($form) {
            let deferred = $.Deferred(),
                validationResult,
                self = this;

            if (!$form.length) {
                return deferred.resolve().promise();
            }

            $.data($form[0], 'validator', null);

            $form.validate({
                /**
                 * Ignores elements with .skip-submit, .no-display ancestor elements
                 */
                ignore: function () {
                    return jQuery(this).closest('.skip-submit, .no-display').length;
                },
                errorClass: 'mage-error'
            });

            validationResult = $form.valid();

            if (!validationResult) {
                return deferred.reject().promise();
            }

            let formElements = [],
                i = 0;
            Form.getElements($form[0]).each(function (e) {

                if (jQuery(e).closest('.skip-submit, .no-display').length === 0) {
                    formElements[i] = e;
                    i++;
                }
            });

            let params = this.serializeElements(formElements);
            let widgetData = this.getWidgetData();
            params["form_key"] = window.FORM_KEY;
            params["widget_type"] = widgetData.type;
            this.widgetData(params);

            let requestData = {
                method: 'POST',
                data: params
            };
            if (_.isEmpty(params["widget_type"])) {
                return deferred.resolve().promise();
            }
            $('body').trigger('processStart');
            $.ajax(objectUtils.nested(Config.getConfig(), this.saveFormUrlConfigPath), requestData)
                .always(function () {
                    $('body').trigger('processStop');
                }.bind(this))
                .done(function (response) {
                    let contentSettings,
                        regex,
                        match,
                        result;

                    if (
                        !response ||
                        _.isUndefined(response.widgetDeclaration) ||
                        response.widgetDeclaration === null ||
                        _.isUndefined(response.widgetData) ||
                        response.widgetData === null
                    ) {
                        self.errorMessage(this.messages.UNKOWN_ERROR);
                        deferred.reject();

                        return;
                    }

                    self.errorMessage(null);
                    regex = /(\w+)="([^"]*)"/g;
                    result = {widget_type: widgetData.type, values: {}};

                    while ((match = regex.exec(response.widgetData)) !== null) {
                        result.values[match[1]] = match[2];
                    }

                    contentSettings = registry.get(this.parentName + '.content_settings');
                    if (!contentSettings) {
                        self.errorMessage(this.messages.UNKOWN_ERROR);
                        deferred.reject();

                        return;
                    }

                    contentSettings.value(JSON.stringify({
                        "data": JSON.stringify(result),
                        "preview": response.widgetPreview,
                        "code": self.selectedWidgetCode()
                    }));

                    self.value(response.widgetDeclaration);
                    deferred.resolve(response);

                }.bind(this))
                .fail(function () {
                    self.errorMessage(this.messages.UNKOWN_ERROR);
                    deferred.reject();
                }.bind(this));

            return deferred.promise();
        },

        getWidgetData: function () {
            let widgetCode = this.selectedWidgetCode();
            let widgetData = {
                'type': '',
                'code': $t('-- Please Select --'),
                'name': $t('-- Please Select --'),
                'description': ''
            };
            _.forEach(this.chooserOptions(), function(option) {
                if (option.code === widgetCode) {
                    widgetData = option;
                }
            });
            return widgetData;
        },

        serializeElements: function (elements) {
            let options = {hash: true, submit: true},
                key,
                value,
                submitted = false,
                submit = options.submit,
                accumulator,
                initial;

            if (options.hash) {
                initial = {};
                accumulator = function (result, key, value) {
                    if (key in result) {
                        if (!Object.isArray(result[key])) result[key] = [result[key]];
                        result[key] = result[key].concat(value);
                    } else result[key] = value;
                    return result;
                };
            }

            return elements.inject(initial, function (result, element) {
                if (!element.disabled && element.name) {
                    key = element.name;
                    value = $('#' + $(element).attr('id')).val();
                    if (value != null && element.type != 'file' && (element.type != 'submit' || (!submitted &&
                        submit !== false && (!submit || key == submit) && (submitted = true)))) {
                        result = accumulator(result, key, value);
                    }
                }
                return result;
            });
        },

        renderWidgetBuilder: function () {
            if (_.isEmpty(this.value())) {
                this.renderWidgetSelect();
            } else {
                let decodedValue = JSON.parse(registry.get(this.parentName + '.content_settings').value());
                this.renderWidgetSelect(decodedValue.code);
                $('body').trigger('processStart');
                let requestData = $.extend(true, {}, this.requestData);
                requestData.data.widget = decodedValue["data"];
                $.ajax(objectUtils.nested(Config.getConfig(), this.formUrlConfigPath), requestData)
                    .always(function () {
                        $('body').trigger('processStop');
                        this.errorMessage(null);
                    }.bind(this))
                    .done(function (response) {
                        this.widgetHtmlForm(response);
                    }.bind(this))
                    .fail(function () {
                        this.errorMessage(this.messages.UNKOWN_ERROR);
                    }.bind(this));
            }
        },

        renderWidgetSelect: function (value = '') {
            let self = this;
            if (_.isEmpty(value)) {
                this.saveIsAvailable(false);
            } else {
                this.saveIsAvailable(true);
                this.selectedWidgetCode(value);
            }
            $('body').trigger('processStart');
            $.ajax(objectUtils.nested(Config.getConfig(), this.dataUrlConfigPath), this.requestData)
                .always(function () {
                    $('body').trigger('processStop');
                    this.errorMessage(null);
                }.bind(this))
                .done(function (response) {
                    if (!_.isArray(response) || response.error) {
                        this.chooserOptions([{
                            'type': '',
                            'code': $t('-- Please Select --'),
                            'name': $t('-- Please Select --'),
                            'description': ''
                        }]);
                        this.errorMessage(this.messages.UNKOWN_ERROR);
                    }
                    _.each(response, function(item, index) {
                        response[index].selected = false;
                        if (_.isEmpty(item.code)) {
                            response[index].code = $t('-- Please Select --');
                        }
                        if (value === item.code) {
                            if (value !== $t('-- Please Select --')) {
                                response[index].selected = true;
                                self.saveIsAvailable(true);
                                self.selectedWidgetCode(value);
                            }
                        }
                    });
                    this.chooserOptions(response);
                }.bind(this))
                .fail(function () {
                    this.chooserOptions([{
                        'type': '',
                        'code': $t('-- Please Select --'),
                        'name': $t('-- Please Select --'),
                        'description': ''
                    }]);
                    this.errorMessage(this.messages.UNKOWN_ERROR);
                }.bind(this));
        },

        initWidget: function (widgetCode) {
            let self = this,
                selectedWidget = null;

            if (
                widgetCode !== $t('-- Please Select --') && this.chooserOptions().length < 2
            ) {
                this.renderWidgetSelect(widgetCode);
            }

            if (!_.isEmpty(widgetCode) && widgetCode !== $t('-- Please Select --')) {
                this.saveIsAvailable(true);
            } else {
                this.saveIsAvailable(false);
            }
            _.forEach(this.chooserOptions(), function (option) {
                if (option.code === widgetCode) {
                    self.selectedWidgetDescription(option.description);
                    selectedWidget = JSON.stringify({
                        "widget_type": option.type,
                        "values": {}
                    });
                }
            });
            if (selectedWidget === null) {
                this.selectedWidgetDescription('');
            } else {
                let requestData = $.extend(true, {}, this.requestData);
                requestData.data.widget = selectedWidget;
                if (this.value() !== "") {
                    let currentValue = JSON.parse(registry.get(this.parentName + '.content_settings').value());

                    $('body').trigger('processStart');
                    if (currentValue["code"] !== widgetCode) {
                        requestData.data.widget = selectedWidget;
                    } else {
                        requestData.data.widget = currentValue["data"];
                    }
                }
                $.ajax(objectUtils.nested(Config.getConfig(), this.formUrlConfigPath), requestData)
                    .always(function () {
                        $('body').trigger('processStop');
                        this.errorMessage(null);
                    }.bind(this))
                    .done(function (response) {
                        self.widgetHtmlForm(response);
                    }.bind(this))
                    .fail(function () {
                        this.errorMessage(this.messages.UNKOWN_ERROR);
                    }.bind(this));
            }
        }
    });
});
