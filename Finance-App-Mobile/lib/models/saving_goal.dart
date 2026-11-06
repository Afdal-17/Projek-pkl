class SavingGoal {
  SavingGoal({
    this.id,
    required this.name,
    required this.description,
    required this.walletName,
    required this.savedIdr,
    required this.targetIdr,
  });

  final dynamic id;
  final String name;
  final String description;
  final String walletName;
  double savedIdr;
  final double targetIdr;

  double get progress =>
      targetIdr == 0 ? 0 : (savedIdr / targetIdr).clamp(0, 1).toDouble();
}