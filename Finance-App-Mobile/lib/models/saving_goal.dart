import 'money.dart';

class SavingGoal {
  SavingGoal({
    this.id,
    required this.name,
    required this.description,
    required this.walletName,
    required this.saved,
    required this.target,
  });

  final dynamic id;
  final String name;
  final String description;
  final String walletName;
  final Money saved;
  final Money target;

  double get progress => target.baseAmount == 0
      ? 0
      : (saved.baseAmount / target.baseAmount).clamp(0, 1).toDouble();
}
