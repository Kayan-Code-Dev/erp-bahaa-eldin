# Mermaid Diagram Files

This directory contains individual Mermaid diagram files (`.mmd`) extracted from the main documentation.

## Files

1. **01-order-creation-flow.mmd** - Complete flow for creating a new order
2. **02-payment-flow.mmd** - How payments are added and processed
3. **03-order-status-transition-flow.mmd** - All possible order status transitions
4. **04-delivered-status-validation-flow.mmd** - Validation rules for marking order as delivered
5. **05-finished-status-validation-flow.mmd** - Validation rules for finishing an order
6. **06-discount-calculation-flow.mmd** - How discounts are calculated (item and order level)
7. **07-complete-order-lifecycle.mmd** - Complete order journey from creation to finish
8. **08-payment-calculation-flow.mmd** - How payments are calculated with fees
9. **09-custody-management-flow.mmd** - How custody is managed through the process
10. **10-error-handling-flow.mmd** - Error handling patterns
11. **11-data-relationships-diagram.mmd** - Database entity relationships (ER diagram)
12. **12-status-transition-decision-tree.mmd** - Decision tree for status transitions
13. **13-frontend-component-structure.mmd** - Suggested frontend component architecture
14. **14-api-request-response-flow.mmd** - API request/response sequence diagrams

## How to Use

### Online Viewer
1. Go to [mermaid.live](https://mermaid.live)
2. Copy the contents of any `.mmd` file
3. Paste into the editor to view the diagram

### VS Code
1. Install the "Markdown Preview Mermaid Support" extension
2. Open any `.mmd` file
3. Use the preview feature to view the diagram

### Command Line (with Mermaid CLI)
```bash
npm install -g @mermaid-js/mermaid-cli
mmdc -i diagrams/01-order-creation-flow.mmd -o output.png
```

### In Documentation
These files can be referenced in markdown files using:
```markdown
```mermaid
[contents of .mmd file]
```
```

## Notes

- All diagrams use Mermaid-compatible syntax
- Special characters have been replaced with text equivalents
- Diagrams are standalone and can be used independently
- For the full documentation with descriptions, see `FRONTEND_DOCUMENTATION.md` and `ORDER_FLOWS_DIAGRAMS.md`


























