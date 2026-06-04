import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();



// resources/js/tabulatorHelpers.js

window.initTabulator = function ({
    tableData = [],
    elementId = 'tabulator-table',
    columns = [],
    onUpdate = null,
    livewireField = '', // <== 新增：Livewire 欄位名
    presetKey = null,
    presetValue = '',
    globalName = null, // ✅ 新增參數
    enableRowContextMenu = true,
}) {
    setTimeout(() => {
        if (!window.Tabulator) {
            console.warn('Tabulator is not loaded for this page.');
            return;
        }

        const tabulatorDiv = document.getElementById(elementId);
        if (!tabulatorDiv) return;

        if (!tabulatorDiv.classList.contains('tabulator-initialized')) {
            const componentId = tabulatorDiv.closest('[wire\\:id]')?.getAttribute('wire:id');

            const tabulator = new window.Tabulator(`#${elementId}`, {
                layout: "fitColumns",
                responsiveLayout: "collapse",
                reactiveData: true,
                data: tableData,
                footerElement: false,
                rowContextMenu: enableRowContextMenu ? [
                    {
                        label: "➕ 新增一列",
                        action: function (e, row) {
                            const index = row.getPosition() + 1;
                            tabulator.addRow(
                                window.generateEmptyRow(columns, presetKey, presetValue),
                                false,
                                index
                            );
                        }
                    },
                    {
                        label: "❌ 刪除此列",
                        action: function (e, row) {
                            row.delete();
                        }
                    }
                ]: [],
                columns: columns,
                rowFormatter: function (row) {
                    const data = row.getData();
                    if (data.data_error === 2) {
                        row.getElement().style.backgroundColor = '#fee2e2';
                    } else {
                        row.getElement().style.backgroundColor = '';
                    }
                },
                // cellEdited: function (cell) {

                // }

            });
            // 🔑 綁定 Tabulator 的鍵盤操作：按 Enter 往右移動
            // tabulator.on("cellEditing", function (cell) {
            // });

            tabulator.on("cellEditing", function (cell) {
                setTimeout(() => {
                    const input = cell.getElement()?.querySelector("input");
                    if (!input) return;

                    const row = cell.getRow();
                    const column = cell.getColumn();
                    const table = cell.getTable();
                    const columns = table.getColumns();

                    input.onkeydown = (e) => {
                        let nextCell = null;

                        // ➤ ENTER：跳下一行
                        if (e.key === "Enter") {
                            e.preventDefault();

                            const currentField = column.getField();
                            const columns = cell.getTable().getColumns();
                            const currentIndex = columns.findIndex(col => col.getField() === currentField);

                            const isLastEditableColumn = (() => {
                                for (let i = currentIndex + 1; i < columns.length; i++) {
                                    const colDef = columns[i].getDefinition();
                                    if (colDef.editor && colDef.editor !== false) {
                                        return false;
                                    }
                                }
                                return true;
                            })();

                            const nextRow = row.getNextRow();
                            if (nextRow) {
                                let targetField = currentField;

                                if (isLastEditableColumn) {
                                    // 若是最右邊的欄位 → 找第一個可編輯欄
                                    for (let i = 0; i < columns.length; i++) {
                                        const colDef = columns[i].getDefinition();
                                        if (colDef.editor && colDef.editor !== false) {
                                            targetField = columns[i].getField();
                                            break;
                                        }
                                    }
                                }

                                const nextCell = nextRow.getCell(targetField);
                                if (nextCell) {
                                    nextCell.edit();
                                }
                            }

                            e.stopPropagation();
                        }



                        // ➤ 左右鍵移動（橫向）
                        if (e.key === "ArrowLeft" || e.key === "ArrowRight") {
                            const cursorPos = input.selectionStart;
                            const valueLength = input.value.length;

                            const movingLeft = e.key === "ArrowLeft";
                            const atBoundary = movingLeft ? (cursorPos === 0) : (cursorPos === valueLength);

                            if (atBoundary) {
                                e.preventDefault();

                                const currentIndex = columns.findIndex(col => col.getField() === column.getField());
                                const offset = movingLeft ? -1 : 1;

                                for (let i = currentIndex + offset; i >= 0 && i < columns.length; i += offset) {
                                    const colDef = columns[i].getDefinition();
                                    if (colDef.editor && colDef.editor !== false) {
                                        const field = columns[i].getField();
                                        const nextCell = row.getCell(field);
                                        if (nextCell) { nextCell.edit(); }
                                        break;
                                    }
                                }
                            }

                            // ❌ 否則不 preventDefault，讓游標自己左右移動
                        }


                        // // ➤ 上下鍵移動（直向）
                        // if (e.key === "ArrowDown" || e.key === "ArrowUp") {
                        //     e.preventDefault();

                        //     const targetRow = e.key === "ArrowDown" ? row.getNextRow() : row.getPrevRow();
                        //     if (targetRow) {
                        //         const field = column.getField();
                        //         const colDef = column.getDefinition();
                        //         if (colDef.editor && colDef.editor !== false) {
                        //             nextCell = targetRow.getCell(field);
                        //         }
                        //     }
                        // }

                        // ➤ 移動並進入編輯模式
                        if (nextCell) {
                            nextCell.edit();
                        }
                    };
                }, 10);


            });

            tabulatorDiv.classList.add('tabulator-initialized');
            // ✅ 將表格儲存到指定全域變數（若有指定）
            if (globalName) {
                window[globalName] = tabulator;
            }
        }
    }, 50);
};

window.generateEmptyRow = function (columns, presetKey = null, presetValue = '') {
    const row = {};
    for (const col of columns) {
        row[col.field] = '';
    }
    if (presetKey) {
        row[presetKey] = presetValue;
    }
    return row;
};


window.listenAndResetSelect = function (eventName, selectId) {
    window.addEventListener(eventName, () => {
        const select = document.getElementById(selectId);
        if (!select) return;

        const componentEl = select.closest('[wire\\:id]');
        const componentId = componentEl?.getAttribute('wire:id');

        select.selectedIndex = 0;
    });
};





// window.syncToLivewire = function (componentId, tabulator, livewireField = 'chnameIndex', callback = null) {
//     const data = tabulator.getData();
//     if (componentId && window.Livewire) {
//         Livewire.find(componentId).set(livewireField, data);
//     }
//     if (typeof callback === 'function') {
//         callback(data);
//     }
// };
