
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <style>
@page {
    size: A4 portrait;
    margin: 7mm;
}

* {
    box-sizing: border-box;
}

body {
    font-family: DejaVu Sans, sans-serif;
    color: #1e293b;
    margin: 0;
    font-size: 9px;
}

.certificate {
    width: auto;
    margin: 0;
    border: 3px double #1e3a8a;
    padding: 7mm 10mm;
    text-align: center;
}

.brand {
    color: #1e3a8a;
    font-size: 15px;
    font-weight: bold;
    letter-spacing: 1px;
}

.subtitle {
    font-size: 7px;
    color: #64748b;
    margin-top: 3px;
    letter-spacing: 2px;
}

.top-line {
    width: 65%;
    margin: 5mm auto 0;
    border-top: 2px solid #d5a84f;
}

.heading {
    margin-top: 6mm;
    font-size: 20px;
    font-weight: bold;
    color: #1e3a8a;
    letter-spacing: 1px;
}

.description {
    font-size: 9px;
    margin-top: 4mm;
    line-height: 1.5;
    color: #475569;
}

.employee-name {
    font-size: 16px;
    color: #172554;
    font-weight: bold;
    margin: 2mm 0 4mm;
    text-transform: uppercase;
}

.identity {
    width: 90%;
    margin: 0 auto;
    border-collapse: collapse;
    text-align: left;
    font-size: 8.5px;
}

.identity td {
    padding: 4px 7px;
    border-bottom: 1px solid #e2e8f0;
    line-height: 1.4;
}

.identity td:first-child {
    width: 27%;
    color: #475569;
    font-weight: bold;
}

.identity td:nth-child(2) {
    width: 4%;
    text-align: center;
}

.identity td:last-child {
    color: #1e293b;
}

.result-description {
    margin-top: 5mm;
    font-size: 9px;
    line-height: 1.5;
    color: #475569;
}

.score {
    width: 50%;
    margin: 5mm auto;
    padding: 4mm;
    border: 1px solid #bfdbfe;
    background: #f1f5ff;
}

.score-label {
    font-size: 8px;
    font-weight: bold;
    letter-spacing: 1.5px;
    color: #64748b;
}

.score-value {
    font-size: 28px;
    font-weight: bold;
    color: #1d4ed8;
    margin-top: 3px;
}

.score-caption {
    font-size: 7px;
    color: #64748b;
    margin-top: 3px;
}

.section-heading {
    margin-top: 5mm;
    margin-bottom: 2mm;
    color: #1e3a8a;
    font-size: 9px;
    font-weight: bold;
    text-align: left;
}

.category-table {
    width: 100%;
    margin: 0 auto;
    border-collapse: collapse;
    font-size: 8px;
}

.category-table th {
    background: #1e3a8a;
    color: white;
    padding: 7px 4px;
    border: 1px solid #1e3a8a;
}

.category-table td {
    border: 1px solid #cbd5e1;
    padding: 6px 4px;
    text-align: center;
}

.category-table td:first-child {
    text-align: left;
    font-weight: bold;
    color: #1e3a8a;
}

.category-table tbody tr:nth-child(even) {
    background: #f8fafc;
}

.certificate-number {
    margin-top: 6mm;
    font-size: 8px;
    line-height: 1.6;
    color: #475569;
}

.certificate-number strong {
    color: #1e3a8a;
}

.status {
    margin-top: 2mm;
    font-size: 8px;
    color: #475569;
}

.footer {
    margin-top: 6mm;
    padding-top: 3mm;
    border-top: 1px solid #cbd5e1;
    font-size: 8px;
    line-height: 1.6;
    color: #64748b;
}

.footer strong {
    color: #1e3a8a;
}

    </style>
</head>

<body>
    <div class="certificate">

        <div class="brand">
            RS PKU MUHAMMADIYAH SUKOHARJO
        </div>

        <div class="subtitle">
            COMPUTER ASSISTED TEST
        </div>

        <div class="top-line"></div>

        <div class="heading">
            SERTIFIKAT HASIL UJIAN
        </div>

        <div class="description">
            Dengan ini menyatakan bahwa:
        </div>

        <div class="employee-name">
            {{ $result['employee']['name'] }}
        </div>

        <table class="identity">
            <tr>
                <td>NIP</td>
                <td>:</td>
                <td>{{ $result['employee']['nip'] ?? '-' }}</td>
            </tr>
            <tr>
                <td>Jabatan</td>
                <td>:</td>
                <td>{{ $result['employee']['position'] ?? '-' }}</td>
            </tr>
            <tr>
                <td>Unit Kerja</td>
                <td>:</td>
                <td>{{ $result['employee']['unit'] ?? '-' }}</td>
            </tr>
            <tr>
                <td>Nama Ujian</td>
                <td>:</td>
                <td>{{ $result['exam']['name'] ?? '-' }}</td>
            </tr>
        </table>

        <div class="result-description">
            Telah mengikuti ujian Computer Assisted Test
            dengan hasil sebagai berikut:
        </div>

        <div class="score">
            <div class="score-label">
                NILAI AKHIR
            </div>

            <div class="score-value">
                {{ number_format($result['score'], 2, ',', '.') }}
            </div>

            <div class="score-caption">
                DARI 100
            </div>
        </div>

        <div class="section-heading">
            RINCIAN HASIL UJIAN
        </div>

        <table class="category-table">
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th>Benar</th>
                    <th>Salah</th>
                    <th>Tidak Dijawab</th>
                    <th>Nilai</th>
                </tr>
            </thead>

            <tbody>
                @foreach($result['categories'] as $category)
                    <tr>
                        <td>{{ $category['name'] }}</td>
                        <td>
                            {{ $category['correct'] }}/{{ $category['total'] }}
                        </td>
                        <td>{{ $category['wrong'] }}</td>
                        <td>{{ $category['unanswered'] }}</td>
                        <td>
                            {{ number_format($category['score'], 2, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="certificate-number">
            <strong>Nomor Sertifikat:</strong>
            {{ $result['certificate_number'] }}
            <br>

            <strong>Tanggal Ujian:</strong>
            {{ $result['date'] }}
        </div>

        <div class="status">
            Status Ujian:
            <strong>{{ $result['status_label'] }}</strong>
        </div>

        <div class="footer">
            Sertifikat ini merupakan bukti keikutsertaan
            dan hasil ujian Computer Assisted Test.
            <br>
            <strong>RS PKU Muhammadiyah Sukoharjo</strong>
        </div>

    </div>
</body>
</html>
