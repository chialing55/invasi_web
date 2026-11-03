# PHP／Laravel 純數字陣列鍵規範

## 為什麼需要這份規範

PHP 會自動把可解析為十進位整數的字串陣列鍵轉成 `int`：

```php
$values = ['09' => 'a', '10' => 'b'];

// 實際鍵值：string(2) "09"、int(10)
```

因此資料庫回傳的生育地代碼即使原本是字串，經過 `pluck()`、`keyBy()`、`groupBy()`、`mapWithKeys()` 或轉成 PHP array 後，`'10'` 可能成為 `10`，但 `'09'` 仍是字串。這會造成：

- `array_merge()` 將 `10`、`11` 等整數鍵重新編號；
- Livewire／JSON 往返後鍵的型別或順序改變；
- `is_string()`、嚴格比較及 authoritative-state 驗證失敗；
- 使用 `?? 0` 時，真正的統計值找不到而靜默顯示為 0。

本專案曾因此發生：

1. 成果圖表表 3 從生育地代碼 10 開始，歸化物種平均覆蓋度錯誤顯示為 0。
2. 生育地完成例外只要包含代碼 10 以上，就被誤判為「生育地清單已變更」。

## 強制規範

### 1. 業務識別碼不可直接作為 PHP map key

生育地、樣區、物種、年度或其他可能是純數字字串的識別碼，必須先轉成帶前綴的 opaque key：

```php
private static function habitatKey(int|string $code): string
{
    return 'hab:'.(string) $code;
}

$map[self::habitatKey($code)] = $value;
$value = $map[self::habitatKey($code)] ?? null;
```

前綴需表示 key 的領域，例如 `hab:10`、`plot:001`、`species:12345`、`year:2026`。建立與讀取必須共用同一個函式，不可在不同位置手動拼接；生育地代碼一律使用 `HabitatCode::mapKey()`。

### 2. map 不得使用 `array_merge()` 或 Collection `merge()`

這兩種操作會重新編號整數鍵，只能用於明確的 list：

```php
// 錯誤：若 $base 含 int key，鍵會被重新編號
$result = array_merge($base, $additional);
$result = collect($base)->merge($additional);

// map 已使用非數字 opaque key 時才可這樣合併
$result = array_replace($base, $additional);

// 或明確逐鍵寫入
foreach ($additional as $key => $value) {
    $result[$key] = $value;
}
```

如果資料本質是 list，合併後應明確呼叫 `array_values()` 或 `values()`，表達重新編號是預期行為。

### 3. 不可只靠補零保證字串鍵

`str_pad($code, 2, '0', STR_PAD_LEFT)` 不是可靠的 map-key 解法：`'09'` 會保留字串型別，但 `'10'` 仍會轉成整數鍵。補零只用於顯示或資料格式正規化，map key 仍需加非數字前綴。

### 4. Livewire 公開狀態使用穩定的 opaque key

傳給瀏覽器再送回伺服器的 `group_key`、row key 或選項 key，必須從一開始就帶領域前綴：

```php
$groups['hab:'.implode('+', $codes)] = $codes;
```

不要先使用純數字鍵，再期待 hydration 後維持原型別。

### 5. 缺值回填 0 前必須確認索引安全

以下寫法會掩蓋 key 不一致問題：

```php
$coverage = $coverageByHabitat[$habitat] ?? 0;
```

使用前應確認 map 的建立端與讀取端使用相同 key helper。新增統計時，也應用一筆已知非零資料驗證代碼 10 以上的結果。

## 必要測試

凡新增或修改「識別碼作為 key」的功能，測試至少涵蓋：

- 前導零字串：`'09'`；
- 第一個會被 PHP 轉成整數的代碼：`'10'`；
- 相鄰代碼：`'11'`；
- 同一代碼的字串及整數輸入：`'10'`、`10`；
- map 合併前後仍可由 canonical key 取值；
- 若經 Livewire 傳輸，需驗證載入、修改、送出及伺服器端驗證的完整流程；
- 缺值為 0 與「索引錯誤而回填 0」必須能區分。

## Code review 檢查清單

看到下列程式時，必須檢查 key 是否可能是純數字識別碼：

- `array_merge()`、`Collection::merge()`；
- `pluck($value, $key)`、`keyBy()`、`groupBy()`、`mapWithKeys()`；
- `->all()`、`->toArray()`；
- `$map[$code]`、`array_fill_keys()`；
- `?? 0`、`isset($map[$code])`；
- Livewire 的 `wire:model` 巢狀陣列與 `group_key`；
- JSON encode/decode 前後的 map。

若 key 是識別碼，優先改為 opaque key；若只是排序後的連續資料，應明確使用 list 並呼叫 `values()`。

## 快速診斷

懷疑鍵型別問題時，先輸出 key 與型別，不要只看值：

```php
foreach (array_keys($map) as $key) {
    dump($key, gettype($key));
}
```

若錯誤剛好從 `09` 與 `10` 的交界開始，應優先檢查純數字陣列鍵、map 合併及 Livewire hydration。
