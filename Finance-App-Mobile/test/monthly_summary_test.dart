import 'package:flutter_test/flutter_test.dart';

void main() {
  group('Monthly Summary Calculation Tests', () {
    // Simulasi fungsi kalkulasi yang sama persis dengan yang diimplementasikan di HomeScreen
    Map<String, double> calculateMonthlySummary({
      required List<String> myWalletNames,
      required List<Map<String, dynamic>> rawTxList,
      required DateTime referenceDate,
    }) {
      double inc = 0;
      double exp = 0;
      final seenTxIds = <dynamic>{};

      final currentYear = referenceDate.year;
      final currentMonth = referenceDate.month;

      final normalizedMyWallets =
          myWalletNames.map((w) => w.trim().toLowerCase()).toSet();

      for (final t in rawTxList) {
        // 1. Hindari duplikasi perhitungan
        final txId = t['id'] ?? t['id_transaksi'];
        if (txId != null) {
          if (seenTxIds.contains(txId)) continue;
          seenTxIds.add(txId);
        }

        // 2. Pastikan hanya transaksi bulan dan tahun berjalan yang dihitung
        final dateStr = (t['date'] ?? t['tanggal'])?.toString();
        if (dateStr != null && dateStr.isNotEmpty) {
          final parsed = DateTime.tryParse(dateStr);
          if (parsed != null) {
            if (parsed.year != currentYear || parsed.month != currentMonth) {
              continue; // Lewati transaksi bulan sebelumnya
            }
          }
        }

        final amtRaw = t['amount'] ?? t['nominal'] ?? 0;
        final amt = (amtRaw is num)
            ? amtRaw.toDouble().abs()
            : (double.tryParse(amtRaw.toString())?.abs() ?? 0.0);

        final type = (t['type'] ?? '').toString().toLowerCase();
        final jenis = (t['jenis'] ?? '').toString().toLowerCase();
        final category =
            (t['category'] ?? t['kategori'] ?? '').toString().toLowerCase();

        final isTransfer = type == 'transfer' || category == 'transfer';

        if (isTransfer) {
          final to = (t['to'] ?? '').toString().trim().toLowerCase();
          final wallet = (t['wallet'] ?? '').toString().trim().toLowerCase();

          final isToMyWallet = normalizedMyWallets.contains(to);
          final isFromMyWallet = normalizedMyWallets.contains(wallet);

          // Transfer antar dompet milik sendiri -> TIDAK dihitung sebagai pengeluaran maupun pemasukan
          if (isFromMyWallet && isToMyWallet) {
            continue;
          }

          // Transfer ke pengguna lain (pengeluaran):
          // Sumber adalah dompet user, tujuan bukan dompet user
          if (jenis == 'pengeluaran' || (!isToMyWallet && isFromMyWallet)) {
            exp += amt;
          } else if (jenis == 'pemasukan' || (isToMyWallet && !isFromMyWallet)) {
            // Transfer masuk dari pengguna lain (pemasukan)
            inc += amt;
          }
        } else {
          // Transaksi biasa (non-transfer)
          final isIncome = type == 'income' || jenis == 'pemasukan';
          final isExpense = type == 'expense' || jenis == 'pengeluaran';
          if (isIncome) {
            inc += amt;
          } else if (isExpense) {
            exp += amt;
          }
        }
      }

      return {'income': inc, 'expense': exp};
    }

    final refDate = DateTime(2026, 11, 16);
    final myWallets = ['Cash', 'BCA', 'BNI'];

    test('Transfer kepada pengguna lain dihitung sebagai pengeluaran', () {
      final txList = [
        {
          'id': 1,
          'name': 'Transfer ke John Doe',
          'type': 'transfer',
          'category': 'Transfer',
          'wallet': 'BCA',
          'to': 'John Doe',
          'amount': 50000.0,
          'jenis': 'pengeluaran',
          'date': '2026-11-10',
        },
      ];

      final res = calculateMonthlySummary(
        myWalletNames: myWallets,
        rawTxList: txList,
        referenceDate: refDate,
      );

      expect(res['expense'], 50000.0);
      expect(res['income'], 0.0);
    });

    test('Transfer antardompet milik sendiri TIDAK dihitung sebagai pengeluaran atau pemasukan', () {
      final txList = [
        // Sisi dompet asal (BCA -> Cash)
        {
          'id': 1,
          'name': 'Transfer ke Cash',
          'type': 'transfer',
          'category': 'Transfer',
          'wallet': 'BCA',
          'to': 'Cash',
          'amount': 100000.0,
          'jenis': 'pengeluaran',
          'date': '2026-11-12',
        },
        // Sisi dompet tujuan (Cash <- BCA)
        {
          'id': 2,
          'name': 'Transfer dari BCA',
          'type': 'transfer',
          'category': 'Transfer',
          'wallet': 'Cash',
          'to': 'BCA',
          'amount': 100000.0,
          'jenis': 'pemasukan',
          'date': '2026-11-12',
        },
      ];

      final res = calculateMonthlySummary(
        myWalletNames: myWallets,
        rawTxList: txList,
        referenceDate: refDate,
      );

      expect(res['expense'], 0.0);
      expect(res['income'], 0.0);
    });

    test('Transaksi bulan sebelumnya tidak masuk ke perhitungan bulan berjalan', () {
      final txList = [
        {
          'id': 1,
          'name': 'Beli Kopi Bulan Lalu',
          'type': 'expense',
          'category': 'Food',
          'wallet': 'Cash',
          'amount': 25000.0,
          'jenis': 'pengeluaran',
          'date': '2026-10-25', // Bulan Oktober (bulan lalu)
        },
        {
          'id': 2,
          'name': 'Gaji Bulan Ini',
          'type': 'income',
          'category': 'Salary',
          'wallet': 'BCA',
          'amount': 5000000.0,
          'jenis': 'pemasukan',
          'date': '2026-11-01', // Bulan November (bulan ini)
        },
        {
          'id': 3,
          'name': 'Beli Buku Bulan Ini',
          'type': 'expense',
          'category': 'Education',
          'wallet': 'Cash',
          'amount': 75000.0,
          'jenis': 'pengeluaran',
          'date': '2026-11-15', // Bulan November (bulan ini)
        },
      ];

      final res = calculateMonthlySummary(
        myWalletNames: myWallets,
        rawTxList: txList,
        referenceDate: refDate,
      );

      expect(res['income'], 5000000.0);
      expect(res['expense'], 75000.0); // 25.000 dari Oktober TIDAK dihitung
    });

    test('Transaksi dengan id duplikat tidak dihitung dua kali', () {
      final txList = [
        {
          'id': 1,
          'name': 'Belanja Supermarket',
          'type': 'expense',
          'category': 'Shopping',
          'wallet': 'BCA',
          'amount': 150000.0,
          'jenis': 'pengeluaran',
          'date': '2026-11-05',
        },
        // Duplikat id: 1
        {
          'id': 1,
          'name': 'Belanja Supermarket',
          'type': 'expense',
          'category': 'Shopping',
          'wallet': 'BCA',
          'amount': 150000.0,
          'jenis': 'pengeluaran',
          'date': '2026-11-05',
        },
      ];

      final res = calculateMonthlySummary(
        myWalletNames: myWallets,
        rawTxList: txList,
        referenceDate: refDate,
      );

      expect(res['expense'], 150000.0); // Hanya dihitung 1x
    });
  });
}
