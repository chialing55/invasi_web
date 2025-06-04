import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

// import 'tom-select/dist/css/tom-select.css';
// import TomSelect from 'tom-select';
// window.TomSelect = TomSelect;


// resources/js/tabulatorHelpers.js

window.Tabulator = Tabulator;
window.initTabulator = function ({
    tableData = [],
    elementId = 'tabulator-table',
    columns = [],
    onUpdate = null,
    livewireField = 'chnameIndex', // <== 新增：Livewire 欄位名
    presetKey = null,
    presetValue = '',
    globalName = null, // ✅ 新增參數
}) {
    setTimeout(() => {
        const tabulatorDiv = document.getElementById(elementId);
        if (!tabulatorDiv) return;

        if (!tabulatorDiv.classList.contains('tabulator-initialized')) {
            const componentId = tabulatorDiv.closest('[wire\\:id]')?.getAttribute('wire:id');

            const tabulator = new Tabulator(`#${elementId}`, {
                layout: "fitColumns",
                responsiveLayout: "collapse",
                reactiveData: true,
                data: tableData,
                footerElement: false,
                rowContextMenu: [
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
                ],
                columns: columns,
                // cellEdited: function (cell) {

                // }

            });
            // 🔑 綁定 Tabulator 的鍵盤操作：按 Enter 往右移動
            // tabulator.on("cellEditing", function (cell) {
            //     console.log("🧪 cellEditing fired:", cell.getField());
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

                        // ➤ ENTER：跳下一個可編輯欄位（橫向）
                        if (e.key === "Enter") {
                            e.preventDefault();

                            const currentField = column.getField();
                            let currentIndex = columns.findIndex(col => col.getField() === currentField);

                            for (let i = currentIndex + 1; i < columns.length; i++) {
                                const colDef = columns[i].getDefinition();
                                if (colDef.editor && colDef.editor !== false) {
                                    const field = columns[i].getField();
                                    nextCell = row.getCell(field);
                                    break;
                                }
                            }

                            // 換行
                            if (!nextCell) {
                                const nextRow = row.getNextRow();
                                if (nextRow) {
                                    for (let i = 0; i < columns.length; i++) {
                                        const colDef = columns[i].getDefinition();
                                        if (colDef.editor && colDef.editor !== false) {
                                            const field = columns[i].getField();
                                            nextCell = nextRow.getCell(field);
                                            break;
                                        }
                                    }
                                }
                            }
                        }

                        // ➤ 左右鍵移動（橫向）
                        if (e.key === "ArrowRight" || e.key === "ArrowLeft") {
                            e.preventDefault();

                            const currentIndex = columns.findIndex(col => col.getField() === column.getField());
                            const offset = e.key === "ArrowRight" ? 1 : -1;

                            for (let i = currentIndex + offset; i >= 0 && i < columns.length; i += offset) {
                                const colDef = columns[i].getDefinition();
                                if (colDef.editor && colDef.editor !== false) {
                                    const field = columns[i].getField();
                                    nextCell = row.getCell(field);
                                    break;
                                }
                            }
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


// 摧毀表格
function resetAndInitTabulator(containerId = 'tabulator-table') {
    const tabulatorDiv = document.getElementById(containerId);

    if (!tabulatorDiv) {
        console.warn(`❌ 找不到 #${containerId}`);
        return;
    }

    // 1. 銷毀舊表格
    if (window.chnameIndexTable instanceof Tabulator) {
        console.log("🧹 銷毀舊 Tabulator");
        window.chnameIndexTable.destroy();
        window.chnameIndexTable = null;
    }

    // 2. 清除 DOM 殘留
    tabulatorDiv.innerHTML = '';
    tabulatorDiv.classList.remove('tabulator', 'tabulator-initialized');

}




// window.syncToLivewire = function (componentId, tabulator, livewireField = 'chnameIndex', callback = null) {
//     const data = tabulator.getData();
//     if (componentId && window.Livewire) {
//         Livewire.find(componentId).set(livewireField, data);
//     }
//     if (typeof callback === 'function') {
//         callback(data);
//     }
// };
