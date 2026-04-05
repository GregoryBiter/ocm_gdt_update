// Функция для отображения уведомлений
function showAlert(type, message) {
    var alertClass = 'alert-' + type;
    var iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    var alert = '<div class="alert ' + alertClass + ' alert-dismissible">';
    alert += '<i class="fa ' + iconClass + '"></i> ' + message;
    alert += '<button type="button" class="close" data-dismiss="alert">&times;</button>';
    alert += '</div>';
    
    $('#alert-container').html(alert);
    
    // Автоматически скрыть через 5 секунд
    setTimeout(function() {
        $('#alert-container .alert').alert('close');
    }, 5000);
}

// Загрузка настроек
function loadSettings() {
    $.ajax({
        url: ajaxUrls.settings,
        dataType: 'json',
        success: function(json) {
            $('#input-server').val(json.module_gdt_updater_server || '');
            $('#input-api-key').val(json.module_gdt_updater_api_key || '');
            $('#input-status').val(json.module_gdt_updater_status || '0');
        },
        error: function() {
            showAlert('danger', errorMsg.occurred);
        }
    });
}

// Сохранение настроек
function saveSettings() {
    var formData = $('#form-settings').serialize();
    
    $.ajax({
        url: ajaxUrls.saveSettings,
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(json) {
            if (json.success) {
                showAlert('success', json.success);
                $('#modal-settings').modal('hide');
                // Перезагружаем страницу после сохранения настроек
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else if (json.error) {
                showAlert('danger', json.error);
            }
        },
        error: function() {
            showAlert('danger', errorMsg.save);
        }
    });
}

// Загрузка доступных модулей
function loadAvailableModules() {
    $.ajax({
        url: ajaxUrls.getAvailableModules,
        dataType: 'json',
        success: function(json) {
            if (json.error) {
                $('#install-modules-content').html('<div class="no-available-modules"><i class="fa fa-exclamation-triangle"></i><h3>' + errorMsg.occurred + '</h3><p>' + json.error + '</p></div>');
            } else if (json.modules) {
                renderAvailableModules(json.modules);
            }
        },
        error: function() {
            $('#install-modules-content').html('<div class="no-available-modules"><i class="fa fa-exclamation-triangle"></i><h3>' + errorMsg.check + '</h3><p>' + errorMsg.server + '</p></div>');
        }
    });
}

// Отображение доступных модулей
function renderAvailableModules(modules) {
    var html = '';
    
    if (modules.length === 0) {
        html = '<div class="no-available-modules"><i class="fa fa-puzzle-piece"></i><h3>' + textMsg.noModulesFound + '</h3><p>' + textMsg.noModulesDesc + '</p></div>';
    } else {
        // Добавляем поиск и фильтры
        html += '<div class="install-modules-search">';
        html += '<input type="text" id="modules-search" placeholder="' + textMsg.search + '" />';
        html += '</div>';
        
        html += '<div class="install-modules-filter">';
        html += '<button class="filter-btn active" data-filter="all">' + textMsg.selectAll + ' (' + modules.length + ')</button>';
        html += '</div>';
        
        html += '<div class="install-modules-stats">';
        html += '<span>' + textMsg.noModulesFound + ': <strong>' + modules.length + '</strong></span>';
        html += '</div>';
        
        html += '<div class="install-modules-grid">';
        
        modules.forEach(function(module) {
            html += '<div class="install-module-card" data-module="' + module.code + '">';
            
            html += '<div class="install-module-header">';
            html += '<h3 class="install-module-title">' + module.name + '</h3>';
            html += '<span class="install-module-version">v' + module.version + '</span>';
            html += '</div>';
            
            html += '<div class="install-module-description">' + module.description + '</div>';
            
            html += '<div class="install-module-meta">';
            if (module.author) {
                html += '<div class="install-module-meta-item">';
                html += '<span class="install-module-meta-label">' + textMsg.author + ':</span>';
                html += '<span>' + module.author + '</span>';
                html += '</div>';
            }
            if (module.size) {
                html += '<div class="install-module-meta-item">';
                html += '<span class="install-module-meta-label">Размер:</span>';
                html += '<span>' + module.size + '</span>';
                html += '</div>';
            }
            if (module.downloads) {
                html += '<div class="install-module-meta-item">';
                html += '<span class="install-module-meta-label">Загрузок:</span>';
                html += '<span>' + module.downloads + '</span>';
                html += '</div>';
            }
            html += '</div>';
            
            html += '<div class="install-module-actions">';
            if (module.is_installed) {
                html += '<span class="install-module-installed"><i class="fa fa-check"></i> ' + textMsg.statusActual + '</span>';
            } else {
                html += '<button class="install-module-btn" data-code="' + module.code + '" data-url="' + module.download_url + '">';
                html += '<i class="fa fa-download"></i> ' + textMsg.buttonInstall;
                html += '</button>';
            }
            
            if (module.demo_url) {
                html += '<a href="' + module.demo_url + '" target="_blank" class="install-module-link">' + textMsg.buttonConfirm + '</a>';
            }
            html += '</div>';
            
            html += '</div>';
        });
        
        html += '</div>';
    }
    
    $('#install-modules-content').html(html);
}

// Установка модуля
function installModule(moduleCode, downloadUrl, buttonElement) {
    var originalText = $(buttonElement).html();
    
    $.ajax({
        url: ajaxUrls.installModule,
        type: 'POST',
        data: {
            module_code: moduleCode,
            download_url: downloadUrl,
            user_token: userToken
        },
        dataType: 'json',
        beforeSend: function() {
            $(buttonElement).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + textMsg.loading);
        },
        success: function(json) {
            if (json.success) {
                showAlert('success', json.success);
                $(buttonElement).closest('.install-module-actions').html('<span class="install-module-installed"><i class="fa fa-check"></i> ' + textMsg.statusActual + '</span>');
            } else if (json.error) {
                showAlert('danger', json.error);
                $(buttonElement).prop('disabled', false).html(originalText);
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            showAlert('danger', errorMsg.occurred + ': ' + thrownError);
            $(buttonElement).prop('disabled', false).html(originalText);
        }
    });
}

// Обработчики событий
$(document).ready(function() {
    // Кнопка настроек
    $('#button-settings').on('click', function() {
        loadSettings();
        $('#modal-settings').modal('show');
    });
    
    // Кнопка установки модулей
    $('#button-install-modules').on('click', function() {
        $('#modal-install-modules').modal('show');
        loadAvailableModules();
    });
    
    // Сохранение настроек
    $('#button-save-settings').on('click', function() {
        saveSettings();
    });
    
    // Проверка обновлений
    $('#button-check').on('click', function() {
        var button = $(this);
        
        $.ajax({
            url: ajaxUrls.checkUpdates,
            dataType: 'json',
            beforeSend: function() {
                button.button('loading');
            },
            complete: function() {
                button.button('reset');
            },
            success: function(json) {
                if (json.error) {
                    showAlert('danger', json.error);
                } else if (json.success) {
                    showAlert('success', json.success);
                    // Перезагружаем страницу
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            },
            error: function(xhr, ajaxOptions, thrownError) {
                showAlert('danger', errorMsg.check + ': ' + thrownError);
            }
        });
    });
    
    // Обновление модуля
    $(document).on('click', '.update-module', function(e) {
        e.preventDefault();
        
        var element = this;
        var url = $(element).data('url');
        var originalText = $(element).html();
        
        if (!url) {
            showAlert('danger', errorMsg.code);
            return;
        }
        
        $.ajax({
            url: url,
            dataType: 'json',
            beforeSend: function() {
                $(element).html('<i class="fa fa-spinner fa-spin"></i> ' + textMsg.loading).addClass('disabled');
            },
            success: function(json) {
                if (json.error) {
                    showAlert('danger', json.error);
                    $(element).html(originalText).removeClass('disabled');
                } else if (json.success) {
                    showAlert('success', json.success);
                    // Перезагружаем страницу после успешного обновления
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                }
            },
            error: function(xhr, ajaxOptions, thrownError) {
                showAlert('danger', errorMsg.check + ': ' + thrownError);
                $(element).html(originalText).removeClass('disabled');
            }
        });
    });
    
    // Переключение автообновлений
    $(document).on('click', '.auto-update-link', function(e) {
        e.preventDefault();
        
        var element = this;
        var moduleCode = $(element).data('code');
        var currentState = $(element).data('current');
        var newState = currentState === '1' ? '0' : '1';
        var originalHtml = $(element).html();
        
        $.ajax({
            url: ajaxUrls.toggleAutoUpdate,
            type: 'POST',
            data: {
                module_code: moduleCode,
                auto_update: newState,
                user_token: userToken
            },
            dataType: 'json',
            beforeSend: function() {
                $(element).addClass('disabled').html('<i class="fa fa-spinner fa-spin"></i> ' + textMsg.loading);
            },
            success: function(json) {
                $(element).removeClass('disabled');
                if (json.success) {
                    // Обновляем состояние ссылки
                    $(element).data('current', newState);
                    if (newState === '1') {
                        $(element).html('<i class="fa fa-toggle-on"></i> ' + textMsg.on);
                    } else {
                        $(element).html('<i class="fa fa-toggle-off"></i> ' + textMsg.off);
                    }
                    showAlert('success', textMsg.confirmSave);
                } else if (json.error) {
                    $(element).html(originalHtml);
                    showAlert('danger', json.error);
                }
            },
            error: function(xhr, ajaxOptions, thrownError) {
                $(element).removeClass('disabled').html(originalHtml);
                showAlert('danger', errorMsg.save + ': ' + thrownError);
            }
        });
    });
    
    // Установка модуля
    $(document).on('click', '.install-module-btn', function(e) {
        e.preventDefault();
        var moduleCode = $(this).data('code');
        var downloadUrl = $(this).data('url');
        installModule(moduleCode, downloadUrl, this);
    });
    
    // Поиск модулей
    $(document).on('keyup', '#modules-search', function() {
        var searchTerm = $(this).val().toLowerCase();
        $('.install-module-card').each(function() {
            var moduleTitle = $(this).find('.install-module-title').text().toLowerCase();
            var moduleDescription = $(this).find('.install-module-description').text().toLowerCase();
            
            if (moduleTitle.includes(searchTerm) || moduleDescription.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Фильтры модулей
    $(document).on('click', '.filter-btn', function() {
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        
        var filter = $(this).data('filter');
        $('.install-module-card').show();
    });
    
    // === ФУНКЦИИ УДАЛЕНИЯ МОДУЛЕЙ ===
    
    // Выбор всех модулей
    $('#select-all-modules').on('change', function() {
        var isChecked = $(this).is(':checked');
        $('.module-checkbox').not('#select-all-modules').prop('checked', isChecked);
        updateDeleteButtonState();
    });
    
    // Выбор отдельного модуля
    $(document).on('change', '.module-checkbox', function() {
        if (!$(this).is('#select-all-modules')) {
            var totalCheckboxes = $('.module-checkbox').not('#select-all-modules').length;
            var checkedCheckboxes = $('.module-checkbox:checked').not('#select-all-modules').length;
            
            $('#select-all-modules').prop('checked', totalCheckboxes === checkedCheckboxes);
            updateDeleteButtonState();
        }
    });
    
    // Обновление состояния кнопки массового удаления
    function updateDeleteButtonState() {
        var checkedCount = $('.module-checkbox:checked').not('#select-all-modules').length;
        $('#button-delete-selected').prop('disabled', checkedCount === 0);
        
        if (checkedCount > 0) {
            $('#button-delete-selected').text(' ' + textMsg.buttonDeleteSelected + ' (' + checkedCount + ')');
        } else {
            $('#button-delete-selected').text(' ' + textMsg.buttonDeleteSelected);
        }
    }
    
    // Удаление одного модуля
    $(document).on('click', '.delete-module', function(e) {
        e.preventDefault();
        var moduleCode = $(this).data('code');
        var moduleName = $(this).data('name');
        var deleteUrl = $(this).data('url');
        
        showDeleteConfirmation([{code: moduleCode, name: moduleName, url: deleteUrl}], false);
    });
    
    // Массовое удаление модулей
    $('#button-delete-selected').on('click', function() {
        var selectedModules = [];
        $('.module-checkbox:checked').not('#select-all-modules').each(function() {
            var moduleCode = $(this).data('code');
            var moduleItem = $(this).closest('.module-item');
            var moduleName = moduleItem.find('.module-title').text().trim();
            var deleteUrl = moduleItem.find('.delete-module').data('url');
            
            selectedModules.push({
                code: moduleCode,
                name: moduleName,
                url: deleteUrl
            });
        });
        
        if (selectedModules.length > 0) {
            showDeleteConfirmation(selectedModules, true);
        }
    });
    
    // Показ модального окна подтверждения удаления
    function showDeleteConfirmation(modules, isMultiple) {
        var modal = $('#modal-delete-confirm');
        var messageDiv = $('#delete-message');
        var modulesList = $('#modules-to-delete');
        var modulesListUl = $('#delete-modules-list');
        
        if (isMultiple) {
            messageDiv.text(textMsg.deleteMultipleConfirm.replace('%s', modules.length));
            modulesList.show();
            modulesListUl.empty();
            
            modules.forEach(function(module) {
                modulesListUl.append('<li>' + module.name + ' (' + module.code + ')</li>');
            });
        } else {
            messageDiv.text(textMsg.deleteConfirm.replace('%s', modules[0].name));
            modulesList.hide();
        }
        
        // Сохраняем данные модулей для удаления
        modal.data('modules', modules);
        modal.data('isMultiple', isMultiple);
        modal.modal('show');
    }
    
    // Подтверждение удаления
    $('#button-confirm-delete').on('click', function() {
        var modal = $('#modal-delete-confirm');
        var modules = modal.data('modules');
        var isMultiple = modal.data('isMultiple');
        
        modal.modal('hide');
        
        if (isMultiple) {
            deleteMultipleModules(modules);
        } else {
            deleteSingleModule(modules[0]);
        }
    });
    
    // Удаление одного модуля
    function deleteSingleModule(module) {
        $.ajax({
            url: module.url,
            type: 'GET',
            dataType: 'json',
            beforeSend: function() {
                showAlert('info', textMsg.deleting.replace('%s', module.name));
            },
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.success);
                    // Удаляем строку модуля из списка
                    $('.module-item[data-code="' + module.code + '"]').fadeOut(function() {
                        $(this).remove();
                        updateModulesCount();
                    });
                } else if (response.error) {
                    showAlert('danger', response.error);
                } else if (response.warning) {
                    showAlert('warning', response.warning);
                }
            },
            error: function(xhr, status, error) {
                showAlert('danger', errorMsg.occurred + ': ' + error);
            }
        });
    }
    
    // Массовое удаление модулей
    function deleteMultipleModules(modules) {
        var moduleCodes = modules.map(function(module) {
            return module.code;
        });
        
        $.ajax({
            url: ajaxUrls.deleteMultiple,
            type: 'POST',
            data: {
                modules: moduleCodes
            },
            dataType: 'json',
            beforeSend: function() {
                showAlert('info', textMsg.massDeleting);
            },
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.success);
                    // Удаляем все выбранные модули из списка
                    moduleCodes.forEach(function(code) {
                        $('.module-item[data-code="' + code + '"]').fadeOut(function() {
                            $(this).remove();
                        });
                    });
                } else if (response.warning) {
                    showAlert('warning', response.warning);
                } else if (response.error) {
                    showAlert('danger', response.error);
                }
                
                // Сбрасываем выбор
                setTimeout(function() {
                    $('.module-checkbox').prop('checked', false);
                    updateDeleteButtonState();
                    updateModulesCount();
                }, 1000);
            },
            error: function(xhr, status, error) {
                showAlert('danger', errorMsg.massDelete + ': ' + error);
            }
        });
    }
    
    // Обновление счетчика модулей
    function updateModulesCount() {
        var count = $('.module-item').length;
        $('.modules-list-header').find('i').next().text(textMsg.moduleManagement + ' (' + count + ')');
        
        if (count === 0) {
            $('.modules-list').html('<div class="no-modules"><i class="fa fa-puzzle-piece"></i><h3>' + textMsg.noModulesFound + '</h3><p>' + textMsg.noModulesDesc + '</p></div>');
        }
    }
});
