/// Utility translator komprehensif untuk memastikan seluruh UI & respon backend
/// selalu ditampilkan dalam bahasa Inggris 100%, tanpa kata bahasa Indonesia.
class AppTranslator {
  AppTranslator._();

  static const Map<String, String> _wordMap = {
    // Transaksi & Nama
    'belanja': 'Shopping',
    'beli': 'Purchase',
    'beli hp': 'Buy phone',
    'beli hp baru': 'Buy new phone',
    'beli laptop': 'Buy laptop',
    'gadai mobil': 'Pawn car',
    'gadai motor': 'Pawn motorcycle',
    'saldo awal': 'Initial balance',
    'gaji': 'Salary',
    'gaji bulan ini': 'This month\'s salary',
    'gaji pokok': 'Basic salary',
    'bonus': 'Bonus',
    'upah': 'Wage',
    'makan': 'Food & Dining',
    'makanan': 'Food',
    'makan siang': 'Lunch',
    'makan malam': 'Dinner',
    'sarapan': 'Breakfast',
    'jajan': 'Snacks',
    'kopi': 'Coffee',
    'minum': 'Drinks',
    'minuman': 'Beverages',
    'tagihan': 'Bills',
    'listrik': 'Electricity',
    'air': 'Water',
    'pulsa': 'Phone credit',
    'paket data': 'Data plan',
    'internet': 'Internet',
    'wifi': 'Wi-Fi',
    'hiburan': 'Entertainment',
    'nonton': 'Movies',
    'buku': 'Books',
    'transport': 'Transportation',
    'transportasi': 'Transportation',
    'bensin': 'Fuel',
    'bahan bakar': 'Fuel',
    'parkir': 'Parking',
    'tol': 'Toll fee',
    'tabungan': 'Savings',
    'target tabungan': 'Savings goal',
    'investasi': 'Investment',
    'kesehatan': 'Health',
    'obat': 'Medicine',
    'rumah sakit': 'Hospital',
    'dokter': 'Doctor',
    'pendidikan': 'Education',
    'sekolah': 'School fee',
    'kuliah': 'Tuition',
    'kursus': 'Course',
    'hadiah': 'Gift',
    'donasi': 'Donation',
    'sedekah': 'Charity',
    'zakat': 'Zakat',
    'sewa': 'Rent',
    'kontrakan': 'Rent',
    'kos': 'Boarding house',
    'cicilan': 'Installment',
    'kredit': 'Credit',
    'pakaian': 'Clothing',
    'baju': 'Clothing',
    'celana': 'Clothing',
    'sepatu': 'Shoes',
    'elektronik': 'Electronics',
    'gadget': 'Gadget',
    'olahraga': 'Sports',
    'keperluan': 'Essentials',
    'keperluan rumah': 'Home supplies',
    'belanja bulanan': 'Monthly groceries',
    'belanja harian': 'Daily groceries',
    'lainnya': 'Others',
    'umum': 'General',

    // Status & Sistem
    'pemasukan': 'Income',
    'pengeluaran': 'Expense',
    'transfer': 'Transfer',
    'transfer antardompet': 'Transfer between wallets',
    'transfer antar dompet': 'Transfer between wallets',
    'berhasil': 'Success',
    'gagal': 'Failed',
    'tertunda': 'Pending',
    'batal': 'Cancelled',
    'selesai': 'Completed',
    'aktif': 'Active',
    'nonaktif': 'Inactive',
    'utama': 'Primary',
    'dompet': 'Wallet',
    'rekening': 'Account',
    'saldo': 'Balance',
  };

  /// Menerjemahkan nama transaksi atau input dari backend ke bahasa Inggris
  static String translateTransactionName(String raw) {
    var trimmed = raw.trim();
    if (trimmed.isEmpty) return trimmed;
    final lower = trimmed.toLowerCase();

    // Direct match
    if (_wordMap.containsKey(lower)) {
      return _wordMap[lower]!;
    }

    // Pattern: "Transfer ke <Target>"
    final transferKeRegex = RegExp(r'^transfer\s+ke\s+(.+)$', caseSensitive: false);
    final matchKe = transferKeRegex.firstMatch(trimmed);
    if (matchKe != null) {
      final dest = matchKe.group(1)?.trim() ?? '';
      return 'Transfer to $dest';
    }

    // Pattern: "Transfer dari <Source>"
    final transferDariRegex = RegExp(r'^transfer\s+dari\s+(.+)$', caseSensitive: false);
    final matchDari = transferDariRegex.firstMatch(trimmed);
    if (matchDari != null) {
      final source = matchDari.group(1)?.trim() ?? '';
      return 'Transfer from $source';
    }

    // Pattern: "Terima transfer dari <Sender>" atau "Menerima transfer dari <Sender>"
    final terimaTransferRegex = RegExp(
      r'^(?:terima\s+transfer\s+dari|menerima\s+transfer\s+dari|terima\s+transfer)\s+(.+)$',
      caseSensitive: false,
    );
    final matchTerima = terimaTransferRegex.firstMatch(trimmed);
    if (matchTerima != null) {
      final sender = matchTerima.group(1)?.trim() ?? '';
      return 'Received transfer from $sender';
    }

    // Pattern: "Kirim ke <Target>"
    final kirimKeRegex = RegExp(r'^kirim\s+ke\s+(.+)$', caseSensitive: false);
    final matchKirim = kirimKeRegex.firstMatch(trimmed);
    if (matchKirim != null) {
      final dest = matchKirim.group(1)?.trim() ?? '';
      return 'Send to $dest';
    }

    // Partial prefix/suffix match (misal: "beli baju", "belanja bulanan")
    if (lower.startsWith('beli ')) {
      final item = lower.substring(5).trim();
      final translatedItem = _wordMap[item] ?? item;
      return 'Buy $translatedItem';
    }
    if (lower.startsWith('belanja ')) {
      final item = lower.substring(8).trim();
      final translatedItem = _wordMap[item] ?? item;
      return 'Shopping for $translatedItem';
    }
    if (lower.startsWith('bayar ')) {
      final item = lower.substring(6).trim();
      final translatedItem = _wordMap[item] ?? item;
      return 'Pay $translatedItem';
    }
    if (lower.startsWith('pengeluaran ')) {
      final item = lower.substring(12).trim();
      final translatedItem = _wordMap[item] ?? item;
      return 'Expense: $translatedItem';
    }
    if (lower.startsWith('pemasukan ')) {
      final item = lower.substring(10).trim();
      final translatedItem = _wordMap[item] ?? item;
      return 'Income: $translatedItem';
    }

    // Sisa kata penghubung bahasa Indonesia
    trimmed = trimmed
        .replaceAll(RegExp(r'\bke\b', caseSensitive: false), 'to')
        .replaceAll(RegExp(r'\bdari\b', caseSensitive: false), 'from')
        .replaceAll(RegExp(r'\buntuk\b', caseSensitive: false), 'for')
        .replaceAll(RegExp(r'\bpada\b', caseSensitive: false), 'on')
        .replaceAll(RegExp(r'\bdi\b', caseSensitive: false), 'in')
        .replaceAll(RegExp(r'\boleh\b', caseSensitive: false), 'by')
        .replaceAll(RegExp(r'\bdan\b', caseSensitive: false), 'and')
        .replaceAll(RegExp(r'\bsebesar\b', caseSensitive: false), 'of')
        .replaceAll(RegExp(r'\bbaru\b', caseSensitive: false), 'new');

    return trimmed;
  }

  /// Menerjemahkan nama kategori ke bahasa Inggris
  static String translateCategory(String raw) {
    final trimmed = raw.trim();
    if (trimmed.isEmpty) return trimmed;
    final lower = trimmed.toLowerCase();
    return _wordMap[lower] ?? trimmed;
  }

  /// Menerjemahkan pesan notifikasi lengkap dari backend
  static String translateNotification(String raw) {
    var text = raw.trim();
    if (text.isEmpty) return text;

    // Bersihkan titik di akhir kalimat untuk parsing regex yang stabil
    final cleanEnd = text.endsWith('.') ? text.substring(0, text.length - 1).trim() : text;

    // Pattern: "Anda di transfer oleh <Name> of/sebesar <Amount>"
    final andaDiTransferMatch = RegExp(
      r'Anda\s+di\s*transfer\s+oleh\s+(.*?)\s+(?:sebesar|of)\s+(.+)$',
      caseSensitive: false,
    ).firstMatch(cleanEnd);
    if (andaDiTransferMatch != null) {
      final sender = andaDiTransferMatch.group(1)?.trim();
      final amt = andaDiTransferMatch.group(2)?.trim();
      return 'You received a transfer of $amt from $sender.';
    }

    // Pattern: "Transfer berhasil ke <Dest> dari <Source> sebesar/of <Amount>"
    final transferBerhasilMatch = RegExp(
      r'Transfer\s+berhasil\s+ke\s+(.*?)\s+dari\s+(.*?)\s+(?:sebesar|of)\s+(.+)$',
      caseSensitive: false,
    ).firstMatch(cleanEnd);
    if (transferBerhasilMatch != null) {
      final dest = transferBerhasilMatch.group(1)?.trim();
      final source = transferBerhasilMatch.group(2)?.trim();
      final amt = transferBerhasilMatch.group(3)?.trim();
      return 'Transfer of $amt to $dest from $source was successful.';
    }

    // Pattern: "Pemasukan <Name> sebesar/of <Amount> berhasil dicatat"
    final pemasukanMatch = RegExp(
      r'Pemasukan\s+(".*?"|.*?)\s+(?:sebesar|of)\s+(.*?)\s+berhasil\s+dicatat\.?',
      caseSensitive: false,
    ).firstMatch(text);
    if (pemasukanMatch != null) {
      var name = pemasukanMatch.group(1)?.trim() ?? '';
      final amt = pemasukanMatch.group(2)?.trim() ?? '';
      name = _cleanAndTranslateQuoted(name);
      return 'Income "$name" of $amt was recorded successfully.';
    }

    // Pattern: "Pengeluaran <Name> sebesar/of <Amount> berhasil dicatat"
    final pengeluaranMatch = RegExp(
      r'Pengeluaran\s+(".*?"|.*?)\s+(?:sebesar|of)\s+(.*?)\s+berhasil\s+dicatat\.?',
      caseSensitive: false,
    ).firstMatch(text);
    if (pengeluaranMatch != null) {
      var name = pengeluaranMatch.group(1)?.trim() ?? '';
      final amt = pengeluaranMatch.group(2)?.trim() ?? '';
      name = _cleanAndTranslateQuoted(name);
      return 'Expense "$name" of $amt was recorded successfully.';
    }

    // Pattern: "Target tabungan / Target <Name> telah tercapai"
    final targetTercapaiMatch = RegExp(
      r'(?:Target\s+tabungan|Target)\s+(".*?"|.*?)\s+(?:telah\s+tercapai|has\s+been\s+reached)!?\.?',
      caseSensitive: false,
    ).firstMatch(text);
    if (targetTercapaiMatch != null) {
      final name = _cleanAndTranslateQuoted(targetTercapaiMatch.group(1)?.trim() ?? '');
      return 'Savings goal "$name" has been reached!';
    }

    // Pattern: "Target tabungan / Target <Name> berhasil dibuat"
    final targetDibuatMatch = RegExp(
      r'(?:Target\s+tabungan|Target)\s+(".*?"|.*?)\s+(?:berhasil\s+dibuat|was\s+created\s+successfully)\.?$',
      caseSensitive: false,
    ).firstMatch(cleanEnd);
    if (targetDibuatMatch != null) {
      final name = _cleanAndTranslateQuoted(targetDibuatMatch.group(1)?.trim() ?? '');
      return 'Savings goal "$name" was created successfully.';
    }

    // Pattern: "Transfer sebesar/of <Amount> ke <Dest> berhasil"
    final transferMatch = RegExp(
      r'Transfer\s+(?:sebesar|of)\s+(.*?)\s+ke\s+(.*?)\s+berhasil\.?',
      caseSensitive: false,
    ).firstMatch(text);
    if (transferMatch != null) {
      final amt = transferMatch.group(1)?.trim();
      final dest = transferMatch.group(2)?.trim();
      return 'Transfer of $amt to $dest was successful.';
    }

    // Pattern: "Menerima transfer dari <Sender> sebesar/of <Amount>"
    final terimaTransferMatch = RegExp(
      r'Menerima\s+transfer\s+dari\s+(.*?)\s+(?:sebesar|of)\s+(.+)$',
      caseSensitive: false,
    ).firstMatch(cleanEnd);
    if (terimaTransferMatch != null) {
      final sender = terimaTransferMatch.group(1)?.trim();
      final amt = terimaTransferMatch.group(2)?.trim();
      return 'Received transfer of $amt from $sender.';
    }

    // Replacement umum untuk sisa kalimat
    text = text
        .replaceAll(RegExp(r'Anda\s+di\s*transfer\s+oleh', caseSensitive: false), 'You received a transfer from')
        .replaceAll(RegExp(r'di\s*transfer\s+oleh', caseSensitive: false), 'transferred by')
        .replaceAll(RegExp(r'di\s*transfer', caseSensitive: false), 'transferred')
        .replaceAll(RegExp(r'Menerima\s+transfer\s+dari', caseSensitive: false), 'Received transfer from')
        .replaceAll(RegExp(r'Target\s+tabungan', caseSensitive: false), 'Savings goal')
        .replaceAll(RegExp(r'telah\s+tercapai', caseSensitive: false), 'has been reached')
        .replaceAll(RegExp(r'berhasil\s+dicatat', caseSensitive: false), 'was recorded successfully')
        .replaceAll(RegExp(r'berhasil\s+dibuat', caseSensitive: false), 'was created successfully')
        .replaceAll(RegExp(r'berhasil\s+diperbarui', caseSensitive: false), 'was updated successfully')
        .replaceAll(RegExp(r'berhasil\s+dihapus', caseSensitive: false), 'was deleted successfully')
        .replaceAll(RegExp(r'berhasil', caseSensitive: false), 'successful')
        .replaceAll(RegExp(r'\bsebesar\b', caseSensitive: false), 'of')
        .replaceAll(RegExp(r'\bdari\b', caseSensitive: false), 'from')
        .replaceAll(RegExp(r'\bke\b', caseSensitive: false), 'to')
        .replaceAll(RegExp(r'\bPemasukan\b', caseSensitive: false), 'Income')
        .replaceAll(RegExp(r'\bPengeluaran\b', caseSensitive: false), 'Expense')
        .replaceAll(RegExp(r'\bSaldo\s+awal\b', caseSensitive: false), 'Initial balance');

    // Jika ada tanda kutip "belanja" dll di dalam kalimat, terjemahkan isinya
    text = text.replaceAllMapped(RegExp(r'"(.*?)"'), (match) {
      final inner = match.group(1) ?? '';
      return '"${translateTransactionName(inner)}"';
    });

    return text;
  }

  static String _cleanAndTranslateQuoted(String text) {
    var clean = text.replaceAll('"', '').trim();
    return translateTransactionName(clean);
  }
}
