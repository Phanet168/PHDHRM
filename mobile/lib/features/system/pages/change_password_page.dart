import 'package:flutter/material.dart';

import '../../../core/network/api_exception.dart';
import '../../auth/controllers/auth_controller.dart';

/// "ប្ដូរពាក្យសម្ងាត់" — real password-change form wired to
/// POST /auth/change-password on the backend (current + new + confirm).
class ChangePasswordPage extends StatefulWidget {
  const ChangePasswordPage({super.key, required this.authController});

  final AuthController authController;

  @override
  State<ChangePasswordPage> createState() => _ChangePasswordPageState();
}

class _ChangePasswordPageState extends State<ChangePasswordPage> {
  final _formKey = GlobalKey<FormState>();
  final _currentController = TextEditingController();
  final _newController = TextEditingController();
  final _confirmController = TextEditingController();

  bool _obscureCurrent = true;
  bool _obscureNew = true;
  bool _obscureConfirm = true;
  bool _submitting = false;
  String? _errorMessage;

  @override
  void dispose() {
    _currentController.dispose();
    _newController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _errorMessage = null);
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    setState(() => _submitting = true);
    try {
      await widget.authController.changePassword(
        currentPassword: _currentController.text,
        newPassword: _newController.text,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(content: Text('ប្ដូរពាក្យសម្ងាត់បានជោគជ័យ')),
        );
      Navigator.of(context).pop();
    } catch (error) {
      if (!mounted) return;
      setState(() => _errorMessage = extractApiErrorMessage(error));
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F7FA),
      body: SafeArea(
        child: Column(
          children: [
            Container(
              height: 72,
              padding: const EdgeInsets.fromLTRB(16, 20, 16, 12),
              decoration: const BoxDecoration(
                color: Colors.white,
                border: Border(bottom: BorderSide(color: Color(0xFFE2E8E6))),
              ),
              child: Row(
                children: [
                  InkWell(
                    onTap: () => Navigator.of(context).maybePop(),
                    borderRadius: BorderRadius.circular(11),
                    child: const Padding(
                      padding: EdgeInsets.all(4),
                      child: Icon(
                        Icons.arrow_back_ios_new_rounded,
                        size: 18,
                        color: Color(0xFF17201E),
                      ),
                    ),
                  ),
                  const Expanded(
                    child: Text(
                      'ប្ដូរពាក្យសម្ងាត់',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF17201E),
                      ),
                    ),
                  ),
                  const SizedBox(width: 22),
                ],
              ),
            ),
            Expanded(
              child: Form(
                key: _formKey,
                autovalidateMode: AutovalidateMode.onUserInteraction,
                child: ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    if (_errorMessage != null) ...[
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFCE8E8),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFF0B4B4)),
                        ),
                        child: Text(
                          _errorMessage!,
                          style: const TextStyle(
                            fontSize: 12,
                            color: Color(0xFFC83B3B),
                          ),
                        ),
                      ),
                      const SizedBox(height: 14),
                    ],
                    _PasswordField(
                      controller: _currentController,
                      label: 'ពាក្យសម្ងាត់បច្ចុប្បន្ន',
                      obscure: _obscureCurrent,
                      onToggleObscure:
                          () => setState(
                            () => _obscureCurrent = !_obscureCurrent,
                          ),
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'សូមបញ្ចូលពាក្យសម្ងាត់បច្ចុប្បន្ន';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 14),
                    _PasswordField(
                      controller: _newController,
                      label: 'ពាក្យសម្ងាត់ថ្មី',
                      obscure: _obscureNew,
                      onToggleObscure:
                          () => setState(() => _obscureNew = !_obscureNew),
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'សូមបញ្ចូលពាក្យសម្ងាត់ថ្មី';
                        }
                        if (value.length < 8) {
                          return 'ពាក្យសម្ងាត់ត្រូវមានយ៉ាងតិច ៨ តួអក្សរ';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 14),
                    _PasswordField(
                      controller: _confirmController,
                      label: 'បញ្ជាក់ពាក្យសម្ងាត់ថ្មី',
                      obscure: _obscureConfirm,
                      onToggleObscure:
                          () => setState(
                            () => _obscureConfirm = !_obscureConfirm,
                          ),
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'សូមបញ្ជាក់ពាក្យសម្ងាត់ថ្មី';
                        }
                        if (value != _newController.text) {
                          return 'ពាក្យសម្ងាត់មិនត្រូវគ្នាទេ';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 24),
                    SizedBox(
                      width: double.infinity,
                      height: 50,
                      child: GestureDetector(
                        onTap: _submitting ? null : _submit,
                        child: Container(
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color:
                                _submitting
                                    ? const Color(
                                      0xFF0B6B58,
                                    ).withValues(alpha: 0.6)
                                    : const Color(0xFF0B6B58),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child:
                              _submitting
                                  ? const SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      valueColor: AlwaysStoppedAnimation(
                                        Colors.white,
                                      ),
                                    ),
                                  )
                                  : const Text(
                                    'រក្សាទុក',
                                    style: TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.bold,
                                      color: Colors.white,
                                    ),
                                  ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PasswordField extends StatelessWidget {
  const _PasswordField({
    required this.controller,
    required this.label,
    required this.obscure,
    required this.onToggleObscure,
    required this.validator,
  });

  final TextEditingController controller;
  final String label;
  final bool obscure;
  final VoidCallback onToggleObscure;
  final String? Function(String?) validator;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      obscureText: obscure,
      validator: validator,
      style: const TextStyle(fontSize: 13, color: Color(0xFF17201E)),
      decoration: InputDecoration(
        filled: true,
        fillColor: Colors.white,
        labelText: label,
        labelStyle: const TextStyle(fontSize: 12, color: Color(0xFF66736F)),
        prefixIcon: const Icon(
          Icons.lock_outline,
          size: 18,
          color: Color(0xFF66736F),
        ),
        suffixIcon: IconButton(
          onPressed: onToggleObscure,
          icon: Icon(
            obscure ? Icons.visibility_off_outlined : Icons.visibility_outlined,
            size: 18,
            color: const Color(0xFF66736F),
          ),
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFE2E8E6)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFE2E8E6)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFF0B6B58)),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFC83B3B)),
        ),
      ),
    );
  }
}
