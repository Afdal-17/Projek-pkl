import 'package:flutter/material.dart';

enum CategoryType { income, expense }

/// Ikon bawaan. Label dipakai untuk teks di kanan "Category icon".
class CategoryIcon {
  const CategoryIcon(this.icon, this.label);

  final IconData icon;
  final String label;
}

const Map<String, CategoryIcon> categoryIcons = {
  'food': CategoryIcon(Icons.restaurant_outlined, 'Food icon'),
  'bank': CategoryIcon(Icons.account_balance_outlined, 'Bank icon'),
  'car': CategoryIcon(Icons.directions_car_outlined, 'Car icon'),
  'briefcase': CategoryIcon(Icons.work_outline_rounded, 'Briefcase icon'),
  'film': CategoryIcon(Icons.movie_outlined, 'Film icon'),
  'bag': CategoryIcon(Icons.shopping_bag_outlined, 'Shopping icon'),
  'wallet': CategoryIcon(Icons.account_balance_wallet_outlined, 'Wallet icon'),
  'phone': CategoryIcon(Icons.smartphone_outlined, 'Phone icon'),
  'cash': CategoryIcon(Icons.payments_outlined, 'Cash icon'),
  'dollar': CategoryIcon(Icons.attach_money_rounded, 'Dollar icon'),
  'target': CategoryIcon(Icons.track_changes_rounded, 'Target icon'),
  'person': CategoryIcon(Icons.person_outline_rounded, 'Person icon'),
  'shield': CategoryIcon(Icons.shield_outlined, 'Shield icon'),
  'settings': CategoryIcon(Icons.settings_outlined, 'Settings icon'),
  'bell': CategoryIcon(Icons.notifications_none_rounded, 'Bell icon'),
};

class CategoryItem {
  const CategoryItem({
    required this.id,
    required this.name,
    required this.type,
    this.iconKey,
    this.emoji,
  });

  final String id;
  final String name;
  final CategoryType type;

  /// Salah satu dari [categoryIcons]. Kosong jika memakai emoji.
  final String? iconKey;

  /// Emoji buatan user. Jika diisi, emoji dipakai menggantikan ikon.
  final String? emoji;

  bool get isIncome => type == CategoryType.income;

  CategoryItem copyWith({
    String? name,
    CategoryType? type,
    String? iconKey,
    String? emoji,
  }) {
    return CategoryItem(
      id: id,
      name: name ?? this.name,
      type: type ?? this.type,
      iconKey: iconKey ?? this.iconKey,
      emoji: emoji ?? this.emoji,
    );
  }
}

/// Data contoh. Nanti diganti dengan data dari backend.
final List<CategoryItem> sampleCategories = [
  const CategoryItem(
    id: '1',
    name: 'Makan',
    type: CategoryType.expense,
    iconKey: 'food',
  ),
  const CategoryItem(
    id: '2',
    name: 'Music Conser',
    type: CategoryType.expense,
    emoji: '🎵',
  ),
];