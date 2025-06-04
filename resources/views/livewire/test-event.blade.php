<div>
    <button wire:click="fire">觸發事件</button>

    <script>
        window.addEventListener('hello', () => {
            alert('成功收到事件！');
        });
    </script>
</div>

