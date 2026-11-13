<form class="search-bar" action="<?= e(url('rooms.php')) ?>" method="get">
    <label>
        NGÀY NHẬN PHÒNG
        <input
            type="date"
            name="check_in"
            min="<?= date('Y-m-d') ?>"
            value="<?= e($in ?? date('Y-m-d')) ?>"
            required
        >
    </label>

    <label>
        NGÀY TRẢ PHÒNG
        <input
            type="date"
            name="check_out"
            min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
            value="<?= e($out ?? date('Y-m-d', strtotime('+1 day'))) ?>"
            required
        >
    </label>

    <label>
        SỐ PHÒNG
        <select name="quantity">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <option
                    value="<?= $i ?>"
                    <?= (int) ($quantity ?? 1) === $i ? 'selected' : '' ?>
                >
                    <?= $i ?> phòng
                </option>
            <?php endfor; ?>
        </select>
    </label>

    <label>
        TỔNG SỐ KHÁCH
        <select name="guests">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <option
                    value="<?= $i ?>"
                    <?= (int) ($guests ?? 2) === $i ? 'selected' : '' ?>
                >
                    <?= $i ?> khách
                </option>
            <?php endfor; ?>
        </select>
    </label>

    <button class="button" type="submit">TÌM PHÒNG TRỐNG ↗</button>
</form>