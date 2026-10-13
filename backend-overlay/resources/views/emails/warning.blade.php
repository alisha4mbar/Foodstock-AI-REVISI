<!doctype html>
<html lang="id">
<body style="font-family: Arial, sans-serif; color:#1e293b">
    <h2 style="color:#047857">FoodStock AI — Peringatan Dini</h2>
    <p>
        Terdeteksi <strong>{{ $forecast->status === 'shortage' ? 'potensi kekurangan (shortage)' : 'potensi kelebihan (surplus)' }}</strong>
        untuk <strong>{{ $forecast->komoditas->name }}</strong> di <strong>{{ $forecast->wilayah->name }}</strong>
        pada periode {{ $forecast->periode->translatedFormat('F Y') }}.
    </p>
    <ul>
        <li>Stok terakhir: {{ number_format($forecast->stock_value, 2, ',', '.') }} {{ $forecast->komoditas->unit }}</li>
        <li>Proyeksi kebutuhan: {{ number_format($forecast->predicted_value, 2, ',', '.') }} {{ $forecast->komoditas->unit }}</li>
        <li>Akurasi model (MAPE): {{ $forecast->mape }}%</li>
    </ul>
    <p>Silakan buka dashboard FoodStock AI untuk tindak lanjut dan rekomendasi realokasi.</p>
</body>
</html>
