@extends('layouts.app')
@section('title', 'Sản phẩm yêu thích')
@section('content')
<main class="container section-space"><div class="section-head"><div><p class="eyebrow">Danh sách của bạn</p><h1 class="page-title">Sản phẩm yêu thích</h1></div><a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">Tài khoản</a></div>
@if($wishlists->isEmpty())<div class="empty-state"><i class="bi bi-heart"></i><h2>Chưa có sản phẩm yêu thích</h2><p>Lưu lại các thiết kế bạn muốn xem lại sau.</p><a class="btn btn-ocean" href="{{ route('products.index') }}">Khám phá sản phẩm</a></div>
@else <div class="row product-grid g-4">@foreach($wishlists as $wishlist)<div class="col-sm-6 col-lg-3">@php($product=$wishlist->product) @include('shop.product-card')</div>@endforeach</div><div class="mt-4">{{ $wishlists->links() }}</div>@endif</main>
@endsection
