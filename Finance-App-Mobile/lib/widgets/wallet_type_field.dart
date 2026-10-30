import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// Field pilihan tipe dompet. Menu muncul tepat di bawah field
/// dengan lebar yang sama, seperti dropdown di web.
class WalletTypeField extends StatelessWidget {
  const WalletTypeField({
    super.key,
    required this.value,
    required this.options,
    required this.onChanged,
  });

  final String value;
  final List<String> options;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final border = OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: const BorderSide(color: AppColors.line),
    );

    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth;

        return MenuAnchor(
          alignmentOffset: const Offset(0, 4),
          style: MenuStyle(
            backgroundColor: const WidgetStatePropertyAll(Colors.white),
            surfaceTintColor:
                const WidgetStatePropertyAll(Colors.transparent),
            elevation: const WidgetStatePropertyAll(4),
            padding: const WidgetStatePropertyAll(
              EdgeInsets.symmetric(vertical: 6),
            ),
            shape: WidgetStatePropertyAll(
              RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
                side: const BorderSide(color: AppColors.line),
              ),
            ),
            minimumSize: WidgetStatePropertyAll(Size(width, 0)),
            maximumSize: WidgetStatePropertyAll(Size(width, double.infinity)),
          ),
          menuChildren: [
            for (final t in options)
              MenuItemButton(
                onPressed: () => onChanged(t),
                leadingIcon: null,
                trailingIcon: t == value
                    ? const Icon(
                        Icons.check_rounded,
                        size: 18,
                        color: AppColors.brand,
                      )
                    : null,
                style: ButtonStyle(
                  minimumSize: WidgetStatePropertyAll(Size(width, 44)),
                  backgroundColor: WidgetStatePropertyAll(
                    t == value ? AppColors.brandSoft : Colors.transparent,
                  ),
                  foregroundColor: WidgetStatePropertyAll(
                    t == value ? AppColors.brand : AppColors.ink,
                  ),
                  padding: const WidgetStatePropertyAll(
                    EdgeInsets.symmetric(horizontal: 16),
                  ),
                  shape: const WidgetStatePropertyAll(
                    RoundedRectangleBorder(),
                  ),
                  textStyle: WidgetStatePropertyAll(
                    TextStyle(
                      fontSize: 15,
                      fontWeight:
                          t == value ? FontWeight.w600 : FontWeight.w400,
                    ),
                  ),
                ),
                child: Text(t),
              ),
          ],
          builder: (context, controller, _) {
            return InkWell(
              borderRadius: BorderRadius.circular(12),
              onTap: () {
                // Tutup keyboard dulu agar menu tidak tertutup atau melayang
                FocusScope.of(context).unfocus();
                if (controller.isOpen) {
                  controller.close();
                } else {
                  controller.open();
                }
              },
              child: InputDecorator(
                isFocused: controller.isOpen,
                isEmpty: false,
                decoration: InputDecoration(
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 12,
                  ),
                  border: border,
                  enabledBorder: border,
                  focusedBorder: border.copyWith(
                    borderSide:
                        const BorderSide(color: AppColors.brand, width: 2),
                  ),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        value,
                        style: const TextStyle(
                          fontSize: 16,
                          color: AppColors.ink,
                        ),
                      ),
                    ),
                    Icon(
                      controller.isOpen
                          ? Icons.keyboard_arrow_up_rounded
                          : Icons.keyboard_arrow_down_rounded,
                      color: AppColors.ink,
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }
}