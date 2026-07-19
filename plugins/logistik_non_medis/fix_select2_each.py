import sys
import re

def fix_select2_init(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        c = f.read()

    # Replace the document ready init
    old_ready = """$(document).ready(function() {
    initSelect2BarangRutin('.select2-barang');"""
    new_ready = """$(document).ready(function() {
    $('.select2-barang').each(function() {
        initSelect2BarangRutin(this);
    });"""
    c = c.replace(old_ready, new_ready)

    # Do the same for terima rutin
    old_ready_terima = """$(document).ready(function() {
    initSelect2BarangTerima('.select2-barang');"""
    new_ready_terima = """$(document).ready(function() {
    $('.select2-barang').each(function() {
        initSelect2BarangTerima(this);
    });"""
    c = c.replace(old_ready_terima, new_ready_terima)
    
    # Do the same for rencana non rutin
    old_ready_nonrutin = """$(document).ready(function() {
    initSelect2BarangRencana('.select2-barang');"""
    new_ready_nonrutin = """$(document).ready(function() {
    $('.select2-barang').each(function() {
        initSelect2BarangRencana(this);
    });"""
    c = c.replace(old_ready_nonrutin, new_ready_nonrutin)

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(c)

paths = [
    r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.rencana_rutin.form.html',
    r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.terima_rutin.form.html',
    r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.rencana_nonrutin.form.html'
]

for p in paths:
    fix_select2_init(p)
