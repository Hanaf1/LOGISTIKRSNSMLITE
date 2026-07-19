import sys

path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f:
    c = f.read()

old_code = '''      echo json_encode([
          'results' => $items,
          'pagination' => [
              'more' => ($page * $per_page) < $total_count
          ]
      ]);
      exit();'''

new_code = '''      header('Content-Type: application/json');
      echo json_encode([
          'results' => $items,
          'pagination' => [
              'more' => ($page * $per_page) < $total_count
          ]
      ]);
      exit();'''

c = c.replace(old_code, new_code)

with open(path, 'w', encoding='utf-8') as f:
    f.write(c)
