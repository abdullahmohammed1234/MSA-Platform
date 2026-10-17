@extends('errors::minimal')

@php($status = $exception->getStatusCode())

@section('title', \Symfony\Component\HttpFoundation\Response::$statusTexts[$status] ?? __('Error'))
@section('code', $status)
@section('message', \Symfony\Component\HttpFoundation\Response::$statusTexts[$status] ?? __('Error'))
