import 'package:flutter/material.dart';
import 'screens/login_screen.dart';
import 'screens/admin/admin_dashboard_screen.dart';
import 'screens/home_screen.dart';
import 'screens/profile_screen.dart';
import 'screens/savings_screen.dart';
import 'screens/transactions_screen.dart';
import 'services/auth_service.dart';
import 'theme/app_theme.dart';
import 'utils/currency.dart';

void main() {
  runApp(const FinanceApp());
}

class FinanceApp extends StatelessWidget {
  const FinanceApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Finance App',
      theme: AppTheme.light(),
      home: const _AuthWrapper(),
    );
  }
}

/// Wrapper untuk mengecek login status dan menampilkan LayerStack atau MainShell.
class _AuthWrapper extends StatefulWidget {
  const _AuthWrapper();

  @override
  State<_AuthWrapper> createState() => _AuthWrapperState();
}

class _AuthWrapperState extends State<_AuthWrapper> {
  bool _isLoggedIn = false;
  bool _isAdmin = false;
  bool _isChecking = true;

  @override
  void initState() {
    super.initState();
    _checkLoginStatus();
  }

  Future<void> _checkLoginStatus() async {
    final loggedIn = await AuthService.isLoggedIn();
    final isAdmin = await AuthService.isAdmin();
    if (mounted) {
      setState(() {
        _isLoggedIn = loggedIn;
        _isAdmin = isAdmin;
        _isChecking = false;
      });
    }
  }

  void _handleLoginSuccess() async {
    final isAdmin = await AuthService.isAdmin();
    if (mounted) {
      setState(() {
        _isLoggedIn = true;
        _isAdmin = isAdmin;
      });
    }
  }

  void _handleLogout() {
    AuthService.logout();
    setState(() {
      _isLoggedIn = false;
      _isAdmin = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_isChecking) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (_isLoggedIn) {
      if (_isAdmin) {
        return AdminDashboardScreen(onExitAdmin: _handleLogout);
      }
      return MainShell(onLogout: _handleLogout);
    }

    return LoginScreen(onLoginSuccess: _handleLoginSuccess);
  }
}

/// Shell utama dengan bottom navigation bar (4 menu).
/// Dibungkus ValueListenableBuilder agar semua layar ikut berubah saat mata uang diganti.
class MainShell extends StatefulWidget {
  const MainShell({super.key, required this.onLogout});

  final VoidCallback onLogout;

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _index = 0;

  void _goTo(int index) => setState(() => _index = index);

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<String>(
      valueListenable: preferredCurrency,
      builder: (context, _, _) {
        final pages = <Widget>[
          const HomeScreen(),
          const TransactionsScreen(),
          const SavingsScreen(),
          ProfileScreen(onLogout: widget.onLogout),
        ];

        return Scaffold(
          backgroundColor: Colors.white,
          body: SafeArea(
            bottom: false,
            child: IndexedStack(index: _index, children: pages),
          ),
          bottomNavigationBar: NavigationBar(
            selectedIndex: _index,
            onDestinationSelected: _goTo,
            destinations: const [
              NavigationDestination(
                icon: Icon(Icons.home_outlined),
                selectedIcon: Icon(Icons.home_rounded),
                label: 'Home',
              ),
              NavigationDestination(
                icon: Icon(Icons.receipt_long_outlined),
                selectedIcon: Icon(Icons.receipt_long_rounded),
                label: 'Transactions',
              ),
              NavigationDestination(
                icon: Icon(Icons.savings_outlined),
                selectedIcon: Icon(Icons.savings_rounded),
                label: 'Savings',
              ),
              NavigationDestination(
                icon: Icon(Icons.person_outline_rounded),
                selectedIcon: Icon(Icons.person_rounded),
                label: 'Account',
              ),
            ],
          ),
        );
      },
    );
  }
}