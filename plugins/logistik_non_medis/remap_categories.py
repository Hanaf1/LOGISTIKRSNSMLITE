import pymysql

# Connect to database
conn = pymysql.connect(host='localhost', user='root', password='', db='mlite_rsns', cursorclass=pymysql.cursors.DictCursor)
cursor = conn.cursor()

rules = {
    'KAT-02': ['printer', 'komputer', 'cpu', 'mouse', 'keyboard', 'laptop', 'monitor', 'switch', 'router', 'hub', 'telepon', 'kabel'],
    'KAT-03': ['ac', 'kipas', 'jam', 'lampu', 'tv', 'televisi', 'dispenser', 'kulkas', 'kabel', 'stop kontak', 'exhaust'],
    'KAT-04': ['ordner', 'staples', 'perforator', 'whiteboard', 'kalkulator', 'gunting', 'kertas', 'pulpen', 'pensil', 'spidol', 'lakban', 'lem'],
    'KAT-05': ['tempat sampah', 'wastafel', 'cermin', 'ember', 'gayung', 'sapu', 'pel', 'kemoceng', 'keset', 'sikat', 'spill kit'],
    'KAT-06': ['keranjang', 'kotak tisu', 'bantal', 'gelas', 'piring', 'sendok', 'garpu', 'guling', 'sprei'],
    'KAT-07': ['gorden', 'wallpaper', 'signage', 'akrilik', 'karpet', 'pigura', 'lukisan', 'poster'],
    'KAT-01': ['meja', 'kursi', 'lemari', 'rak', 'laci', 'sofa', 'bangku', 'bed', 'etalase'],
}

def guess_category(nama_aset):
    nama_lower = nama_aset.lower()
    for kat, keywords in rules.items():
        for kw in keywords:
            if kw in nama_lower:
                return kat
    return 'KAT-08' # Default Peralatan Operasional Unit

updates = 0
cursor.execute("SELECT kode_aset, nama_aset FROM rsns_custom_logistik_non_medis_aset")
assets = cursor.fetchall()

for asset in assets:
    kat = guess_category(asset['nama_aset'])
    cursor.execute("UPDATE rsns_custom_logistik_non_medis_aset SET kode_kategori_aset = %s WHERE kode_aset = %s", (kat, asset['kode_aset']))
    updates += 1

conn.commit()
print(f"Updated {updates} assets")
