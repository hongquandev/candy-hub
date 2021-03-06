@extends('hub::layout')

@section('side_menu')
    @include('hub::catalogue-manager.partials.side-menu')
@endsection

@section('header_title')
    <small>Catalogue Manager</small>
    <h1>Edit Category</h1>
@endsection

@section('header_actions')
    <candy-delete
      element="category"
      endpoint="/categories/{{ $id }}"
      id="{{ $id }}"
      redirect="/catalogue-manager/categories"
      warning="You will lose any child categories along with any associations"
      style="display: inline-block;"
    ></candy-delete>
    <candy-button style="display: inline-block;" event="save-category">Save Category</candy-button>
@endsection

@section('content')
    <candy-category-edit category-id="{{ $id }}"></candy-category-edit>
@endsection