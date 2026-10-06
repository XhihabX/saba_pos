/**
 * Export array of objects to downloadable CSV file in browser
 */
export function exportToCSV(filename, columns, data) {
  if (!data || !data.length) return;

  const headers = columns.map(c => `"${(c.label || c.key).replace(/"/g, '""')}"`).join(',');
  const rows = data.map(item => {
    return columns.map(col => {
      let val = col.key.split('.').reduce((obj, key) => obj?.[key], item);
      if (col.formatter && typeof col.formatter === 'function') {
        val = col.formatter(val, item);
      }
      if (val === null || val === undefined) val = '';
      val = String(val).replace(/"/g, '""');
      return `"${val}"`;
    }).join(',');
  });

  const csvContent = [headers, ...rows].join('\r\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  link.setAttribute('download', `${filename}_${new Date().toISOString().slice(0, 10)}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}
