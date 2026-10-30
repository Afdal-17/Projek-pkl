import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../theme/app_colors.dart';

const _swatchColors = [
  Color(0xFF4F46E5),
  Color(0xFF10B981),
  Color(0xFFEA580C),
  Color(0xFFDC2626),
  Color(0xFF262626),
  Color(0xFF8B5CF6),
  Color(0xFFEC4899),
  Color(0xFF14B8A6),
  Color(0xFF06B6D4),
];

const _hueColors = [
  Color(0xFFFF0000),
  Color(0xFFFFFF00),
  Color(0xFF00FF00),
  Color(0xFF00FFFF),
  Color(0xFF0000FF),
  Color(0xFFFF00FF),
  Color(0xFFFF0000),
];

class ColorPickerField extends StatefulWidget {
  const ColorPickerField({
    super.key,
    required this.color,
    required this.onChanged,
  });

  final Color color;
  final ValueChanged<Color> onChanged;

  @override
  State<ColorPickerField> createState() => _ColorPickerFieldState();
}

class _ColorPickerFieldState extends State<ColorPickerField> {
  static const double _boxHeight = 160;
  static const double _thumbSize = 18;

  late HSVColor _hsv;
  late Color _emitted;
  late final TextEditingController _hexCtrl;

  @override
  void initState() {
    super.initState();
    _hsv = HSVColor.fromColor(widget.color);
    _emitted = widget.color;
    _hexCtrl = TextEditingController(text: _hexOf(widget.color));
  }

  @override
  void didUpdateWidget(covariant ColorPickerField oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.color != _emitted) {
      _hsv = HSVColor.fromColor(widget.color);
      _emitted = widget.color;
      _hexCtrl.text = _hexOf(widget.color);
    }
  }

  @override
  void dispose() {
    _hexCtrl.dispose();
    super.dispose();
  }

  static String _hexOf(Color c) => (c.toARGB32() & 0xFFFFFF)
      .toRadixString(16)
      .padLeft(6, '0')
      .toUpperCase();

  /// Dipanggil saat spectrum atau slider hue digeser.
  /// Hue dipertahankan agar tidak lompat saat saturasi nol.
  void _applyHsv(HSVColor hsv) {
    final c = hsv.toColor();
    setState(() {
      _hsv = hsv;
      _emitted = c;
      _hexCtrl.text = _hexOf(c);
    });
    widget.onChanged(c);
  }

  /// Dipanggil dari swatch atau HEX.
  void _applyColor(Color c, {required bool syncHex}) {
    setState(() {
      _hsv = HSVColor.fromColor(c);
      _emitted = c;
      if (syncHex) _hexCtrl.text = _hexOf(c);
    });
    widget.onChanged(c);
  }

  void _onHexChanged(String value) {
    final clean = value.trim();
    if (clean.length != 6) return;
    final parsed = int.tryParse(clean, radix: 16);
    if (parsed == null) return;
    _applyColor(Color(0xFF000000 | parsed), syncHex: false);
  }

  Widget _thumb(double size) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: Colors.white,
        shape: BoxShape.circle,
        border: Border.all(color: AppColors.ink, width: 2),
        boxShadow: const [
          BoxShadow(color: Color(0x33000000), blurRadius: 4),
        ],
      ),
    );
  }

  Widget _svBox() {
    return LayoutBuilder(
      builder: (context, constraints) {
        final w = constraints.maxWidth;

        void pick(Offset p) {
          final s = (p.dx / w).clamp(0.0, 1.0);
          final v = 1 - (p.dy / _boxHeight).clamp(0.0, 1.0);
          _applyHsv(_hsv.withSaturation(s).withValue(v));
        }

        return GestureDetector(
          behavior: HitTestBehavior.opaque,
          onPanDown: (d) => pick(d.localPosition),
          onPanUpdate: (d) => pick(d.localPosition),
          child: SizedBox(
            height: _boxHeight,
            child: ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Stack(
                children: [
                  Positioned.fill(
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [
                            Colors.white,
                            HSVColor.fromAHSV(1, _hsv.hue, 1, 1).toColor(),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const Positioned.fill(
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: [Colors.transparent, Colors.black],
                        ),
                      ),
                    ),
                  ),
                  Positioned(
                    left: _hsv.saturation * w - _thumbSize / 2,
                    top: (1 - _hsv.value) * _boxHeight - _thumbSize / 2,
                    child: _thumb(_thumbSize),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _hueSlider() {
    return LayoutBuilder(
      builder: (context, constraints) {
        final w = constraints.maxWidth;

        void pick(Offset p) {
          final h = (p.dx / w).clamp(0.0, 1.0) * 360;
          _applyHsv(_hsv.withHue(h));
        }

        return GestureDetector(
          behavior: HitTestBehavior.opaque,
          onPanDown: (d) => pick(d.localPosition),
          onPanUpdate: (d) => pick(d.localPosition),
          child: SizedBox(
            height: 20,
            child: Stack(
              clipBehavior: Clip.none,
              children: [
                Positioned(
                  left: 0,
                  right: 0,
                  top: 2,
                  height: 16,
                  child: Container(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(8),
                      gradient: const LinearGradient(colors: _hueColors),
                    ),
                  ),
                ),
                Positioned(
                  left: (_hsv.hue / 360) * w - _thumbSize / 2,
                  top: 1,
                  child: _thumb(_thumbSize),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  InputDecoration _hexDecoration() {
    final border = OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: const BorderSide(color: AppColors.line),
    );
    return InputDecoration(
      prefixText: '# ',
      prefixStyle: const TextStyle(color: AppColors.muted, fontSize: 16),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      border: border,
      enabledBorder: border,
      focusedBorder: border.copyWith(
        borderSide: const BorderSide(color: AppColors.brand, width: 2),
      ),
    );
  }

  Widget _hexRow() {
    final current = _hsv.toColor();
    return Row(
      children: [
        Container(
          width: 44,
          height: 44,
          decoration: BoxDecoration(
            color: current,
            borderRadius: BorderRadius.circular(10),
          ),
          child: const Icon(Icons.check_rounded, color: Colors.white),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: TextField(
            controller: _hexCtrl,
            onChanged: _onHexChanged,
            textCapitalization: TextCapitalization.characters,
            inputFormatters: [
              FilteringTextInputFormatter.allow(RegExp('[0-9a-fA-F]')),
              LengthLimitingTextInputFormatter(6),
            ],
            style: const TextStyle(fontSize: 16, color: AppColors.ink),
            decoration: _hexDecoration(),
          ),
        ),
      ],
    );
  }

  Widget _swatches() {
    final currentHex = _hexOf(_hsv.toColor());
    return Wrap(
      spacing: 10,
      runSpacing: 10,
      children: [
        for (final c in _swatchColors)
          GestureDetector(
            onTap: () => _applyColor(c, syncHex: true),
            child: Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: c,
                shape: BoxShape.circle,
                border: _hexOf(c) == currentHex
                    ? Border.all(color: AppColors.brand, width: 2.5)
                    : null,
              ),
              child: _hexOf(c) == currentHex
                  ? const Icon(Icons.check_rounded,
                      color: Colors.white, size: 18)
                  : null,
            ),
          ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _svBox(),
        const SizedBox(height: 14),
        _hueSlider(),
        const SizedBox(height: 14),
        _hexRow(),
        const SizedBox(height: 14),
        const Text(
          'Quick swatches',
          style: TextStyle(fontSize: 13, color: AppColors.muted),
        ),
        const SizedBox(height: 8),
        _swatches(),
        const SizedBox(height: 10),
        const Text(
          'Pick any custom color via spectrum, slider, or HEX code. '
          'Colors can be reused across wallets.',
          style: TextStyle(fontSize: 12, height: 1.5, color: AppColors.muted),
        ),
      ],
    );
  }
}