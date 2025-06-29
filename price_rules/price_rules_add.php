<?php
require_once '../functions/db.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';
require_once '../includes/menu.php';
?>

<div class="content">
    <h2 class="mb-4">➕ Yeni Fiyat Kuralı Ekle</h2>

    <form action="price_rules_save.php" method="POST" class="form-container">
        <div class="form-group">
            <label>ID:</label>
            <input type="number" name="id" required>
        </div>

        <div class="form-group">
            <label>Vehicle ID:</label>
            <input type="number" name="vehicle_id">
        </div>

        <div class="form-group">
            <label>Rule ID:</label>
            <input type="number" name="rule_id">
        </div>

        <div class="form-group">
            <label>Rule Name:</label>
            <input type="text" name="rule_name">
        </div>

        <div class="form-group">
            <label>Pickup Geofence ID:</label>
            <input type="text" name="pickup_geofence_id">
        </div>

        <div class="form-group">
            <label>Dropoff Geofence ID:</label>
            <input type="text" name="dropoff_geofence_id">
        </div>

        <div class="form-group">
            <label>Price Rule Type ID:</label>
            <select name="price_rule_type_id" required>
                <option value="1">1 - Kişi Başı</option>
                <option value="2">2 - Araç Başı</option>
                <option value="3">3 - Dinamik</option>
            </select>
        </div>

        <button type="submit" class="btn btn-success">💾 Kaydet</button>
    </form>
</div>

<style>
.form-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
    max-width: 500px;
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
}
.form-group {
    display: flex;
    flex-direction: column;
}
.form-group label {
    font-weight: 500;
    margin-bottom: 4px;
}
.form-group input,
.form-group select {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 14px;
}
.btn-success {
    background-color: #28a745;
    color: white;
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    cursor: pointer;
}
.btn-success:hover {
    background-color: #218838;
}
</style>

<?php require_once '../includes/footer.php'; ?>
