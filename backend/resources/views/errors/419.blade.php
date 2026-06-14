@extends('errors::minimal')

@section('title', localize('session_expired', 'Session Expired'))
@section('code', '419')

@section('message')
    <div style="max-width: 460px; margin: 0 auto; text-align: center;">
        <div style="font-size: 32px; font-weight: 600; margin-bottom: 14px;">សម័យប្រើប្រាស់បានផុតកំណត់</div>
        <div style="font-size: 16px; color: #6b7280; line-height: 1.7; margin-bottom: 22px;">
            សូមចូលប្រើម្តងទៀត ដើម្បីបន្តប្រើប្រាស់ប្រព័ន្ធ។
        </div>
        <a href="{{ route('login') }}"
            style="display: inline-block; padding: 12px 22px; border-radius: 10px; background: #198754; color: #fff; text-decoration: none; font-weight: 600;">
            ចូលប្រើម្តងទៀត
        </a>
    </div>
@endsection
