@extends('layouts.app')

@section('title', 'Add employee')
@section('heading', 'Add employee')
@section('subheading', 'A code, first name and last name are all that is required.')

@section('content')
    <form method="POST" action="{{ route('employees.store') }}">
        @csrf
        @include('employees._form', ['submitLabel' => 'Save employee'])
    </form>
@endsection
