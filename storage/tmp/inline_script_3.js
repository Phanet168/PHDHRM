
        document.addEventListener('DOMContentLoaded', function() {
            var treePanel = document.querySelector('.org-tree-panel');
            if (!treePanel) {
                return;
            }
            var contextMenu = document.getElementById('orgTreeContextMenu');
            var contextTargetItem = null;
            var i18n = window.orgUnitI18n || {};

            function t(key, fallback) {
                return i18n[key] || fallback;
            }

            function setExpanded(item, expanded) {
                item.classList.toggle('is-open', expanded);
                var toggle = item.querySelector('.org-tree-row .org-tree-toggle');
                if (!toggle) {
                    return;
                }
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                var symbol = toggle.querySelector('.toggle-symbol');
                if (symbol) {
                    symbol.textContent = expanded ? '-' : '+';
                }
            }

            var branchItems = treePanel.querySelectorAll('.org-tree-item.has-children');

            branchItems.forEach(function(item) {
                var toggle = item.querySelector('.org-tree-row .org-tree-toggle');
                if (!toggle) {
                    return;
                }

                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var isOpen = item.classList.contains('is-open');
                    setExpanded(item, !isOpen);
                });

                setExpanded(item, false);
            });

            var rootList = treePanel.querySelector('.org-hierarchy-tree');
            if (rootList) {
                Array.prototype.forEach.call(rootList.children, function(child) {
                    if (child.classList && child.classList.contains('org-tree-item') && child.classList.contains('has-children')) {
                        setExpanded(child, true);
                    }
                });
            }

            var activeNode = treePanel.querySelector('.org-tree-node.is-active');
            if (activeNode) {
                var currentItem = activeNode.closest('.org-tree-item');
                while (currentItem) {
                    if (currentItem.classList.contains('has-children')) {
                        setExpanded(currentItem, true);
                    }
                    var parentList = currentItem.parentElement;
                    currentItem = parentList ? parentList.closest('.org-tree-item') : null;
                }
            }

            function openCreateModal(parentId) {
                var modalEl = document.getElementById('create-department');
                if (!modalEl) {
                    return;
                }

                var form = modalEl.querySelector('form.js-org-unit-form');
                if (!form) {
                    return;
                }

                form.reset();
                form.setAttribute('data-forced-parent-id', parentId ? String(parentId) : '');

                var unitTypeSelect = form.querySelector('.js-unit-type-select');
                if (unitTypeSelect) {
                    unitTypeSelect.value = '';
                    unitTypeSelect.dispatchEvent(new Event('change'));
                }

                var parentSelect = form.querySelector('.js-parent-unit-select');
                if (parentSelect) {
                    parentSelect.disabled = false;
                    parentSelect.value = parentId ? String(parentId) : '';
                    if (window.jQuery && window.jQuery(parentSelect).data('select2')) {
                        window.jQuery(parentSelect).trigger('change');
                    }
                }

                var modalTitle = modalEl.querySelector('.modal-title');
                if (modalTitle) {
                    modalTitle.textContent = parentId ? t('addSubUnit', 'Add Sub Unit') : t('addRootUnit', 'Add Root Unit');
                }

                if (window.bootstrap && window.bootstrap.Modal) {
                    var createModal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
                    createModal.show();
                } else if (window.jQuery) {
                    window.jQuery(modalEl).modal('show');
                }
            }
            window.orgUnitOpenCreateModal = openCreateModal;

            function openEditModal(editUrl) {
                if (!editUrl || !window.jQuery) {
                    return;
                }

                window.jQuery.ajax({
                    type: 'GET',
                    dataType: 'html',
                    url: editUrl,
                    success: function(html) {
                        var modal = window.jQuery('#edit-department');
                        modal.find('.modal-content').html(html);
                        modal.modal('show');
                    }
                });
            }

            function deleteUnit(deleteUrl, csrf) {
                if (!deleteUrl || !window.jQuery) {
                    return;
                }

                if (!window.confirm(t('deleteConfirm', 'Delete this org unit?'))) {
                    return;
                }

                window.jQuery.ajax({
                    type: 'POST',
                    url: deleteUrl,
                    data: {
                        _method: 'DELETE',
                        _token: csrf
                    },
                    success: function(response) {
                        if (response && response.success) {
                            window.location.href = 'http://localhost/PHDHRM/hr/departments';
                            return;
                        }

                        window.alert((response && response.message) ? response.message : t('deleteFailed', 'Delete failed.'));
                    },
                    error: function(xhr) {
                        var message = t('deleteFailed', 'Delete failed.');
                        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }

                        window.alert(message);
                    }
                });
            }

            function hideContextMenu() {
                if (!contextMenu) {
                    return;
                }

                contextMenu.classList.remove('is-open');
                contextMenu.setAttribute('aria-hidden', 'true');
                contextMenu.style.left = '-9999px';
                contextMenu.style.top = '-9999px';
                contextTargetItem = null;
            }

            function setContextActionState(action, enabled, payload) {
                if (!contextMenu) {
                    return;
                }

                var item = contextMenu.querySelector('[data-action="' + action + '"]');
                if (!item) {
                    return;
                }

                item.disabled = !enabled;
                item.dataset.payload = payload || '';
            }

            function showContextMenu(item, x, y) {
                if (!contextMenu || !item) {
                    return;
                }

                contextTargetItem = item;
                var addBtn = item.querySelector('.js-node-add-child');
                var editBtn = item.querySelector('.js-node-edit');
                var deleteBtn = item.querySelector('.js-node-delete');

                setContextActionState('add-sub', !!addBtn, addBtn ? addBtn.getAttribute('data-parent-id') : '');
                setContextActionState('edit', !!editBtn, editBtn ? editBtn.getAttribute('data-edit-url') : '');
                setContextActionState(
                    'delete',
                    !!deleteBtn,
                    deleteBtn
                        ? JSON.stringify({
                            url: deleteBtn.getAttribute('data-delete-url') || '',
                            csrf: deleteBtn.getAttribute('data-csrf') || ''
                        })
                        : ''
                );

                contextMenu.style.left = x + 'px';
                contextMenu.style.top = y + 'px';
                contextMenu.classList.add('is-open');
                contextMenu.setAttribute('aria-hidden', 'false');

                var menuRect = contextMenu.getBoundingClientRect();
                var maxLeft = window.innerWidth - menuRect.width - 8;
                var maxTop = window.innerHeight - menuRect.height - 8;

                if (menuRect.left > maxLeft) {
                    contextMenu.style.left = Math.max(8, maxLeft) + 'px';
                }
                if (menuRect.top > maxTop) {
                    contextMenu.style.top = Math.max(8, maxTop) + 'px';
                }
            }

            var rootButton = document.querySelector('.js-open-create-root');
            if (rootButton) {
                rootButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    openCreateModal(null);
                });
            }

            treePanel.addEventListener('click', function(e) {
                if (contextMenu && contextMenu.classList.contains('is-open')) {
                    hideContextMenu();
                }

                var addBtn = e.target.closest('.js-node-add-child');
                if (addBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    var parentId = Number(addBtn.getAttribute('data-parent-id') || 0);
                    if (parentId > 0) {
                        openCreateModal(parentId);
                    }
                    return;
                }

                var editBtn = e.target.closest('.js-node-edit');
                if (editBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    openEditModal(editBtn.getAttribute('data-edit-url'));
                    return;
                }

                var deleteBtn = e.target.closest('.js-node-delete');
                if (deleteBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    deleteUnit(deleteBtn.getAttribute('data-delete-url'), deleteBtn.getAttribute('data-csrf'));
                }
            });

            treePanel.addEventListener('contextmenu', function(e) {
                var node = e.target.closest('.org-tree-node');
                if (!node) {
                    return;
                }

                var item = node.closest('.org-tree-item');
                if (!item) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();
                showContextMenu(item, e.clientX, e.clientY);
            });

            if (contextMenu) {
                contextMenu.addEventListener('click', function(e) {
                    var actionBtn = e.target.closest('.org-context-item[data-action]');
                    if (!actionBtn || actionBtn.disabled) {
                        return;
                    }

                    var action = actionBtn.getAttribute('data-action');
                    if (action === 'add-sub') {
                        var parentId = Number(actionBtn.dataset.payload || 0);
                        if (parentId > 0) {
                            openCreateModal(parentId);
                        }
                    } else if (action === 'edit') {
                        var editUrl = actionBtn.dataset.payload || '';
                        if (editUrl) {
                            openEditModal(editUrl);
                        }
                    } else if (action === 'delete') {
                        try {
                            var parsed = JSON.parse(actionBtn.dataset.payload || '{}');
                            if (parsed.url && parsed.csrf) {
                                deleteUnit(parsed.url, parsed.csrf);
                            }
                        } catch (err) {
                            // ignore invalid payload
                        }
                    } else if (action === 'refresh') {
                        window.location.reload();
                    }

                    hideContextMenu();
                });
            }

            document.addEventListener('click', function(e) {
                if (!contextMenu || !contextMenu.classList.contains('is-open')) {
                    return;
                }

                if (e.target.closest('#orgTreeContextMenu')) {
                    return;
                }

                hideContextMenu();
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    hideContextMenu();
                }
            });
        });
    