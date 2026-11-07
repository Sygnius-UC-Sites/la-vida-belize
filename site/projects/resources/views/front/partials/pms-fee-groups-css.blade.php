<style>
    .pms-fee-groups {
        display: grid;
        gap: 7px;
        margin-bottom: 12px;
    }
    .pms-fee-group {
        overflow: hidden;
        border: 1px solid #ece8e1;
        border-radius: 8px;
        background: #fff;
    }
    .pms-fee-group summary {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 9px 10px;
        color: #29231d;
        cursor: pointer;
        list-style: none;
    }
    .pms-fee-group summary::-webkit-details-marker {
        display: none;
    }
    .pms-fee-group summary::before {
        content: '\203A';
        flex: 0 0 auto;
        color: #9b7b43;
        font-size: 1.15rem;
        line-height: 1;
        transition: transform .15s ease;
    }
    .pms-fee-group[open] summary::before {
        transform: rotate(90deg);
    }
    .pms-fee-group-label {
        display: flex;
        align-items: baseline;
        gap: 6px;
        flex: 1;
        min-width: 0;
        font-weight: 600;
    }
    .pms-fee-group-label small {
        overflow: hidden;
        color: #888;
        font-size: .72rem;
        font-weight: 400;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .pms-fee-group-total {
        flex: 0 0 auto;
        font-weight: 600;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .pms-fee-group-items {
        padding: 2px 10px 8px 28px;
        border-top: 1px solid #f2eee8;
        background: #fbfaf8;
    }
    .pms-fee-group-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 5px 0;
        color: #5d5751;
        font-size: .82rem;
    }
    .pms-fee-group-row span:first-child {
        min-width: 0;
        overflow-wrap: anywhere;
    }
    .pms-fee-group-row span:last-child {
        flex: 0 0 auto;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    @media (max-width: 480px) {
        .pms-fee-group-label {
            display: grid;
            gap: 1px;
        }
        .pms-fee-group-label small {
            white-space: normal;
        }
        .pms-fee-group-row {
            align-items: flex-start;
            gap: 8px;
        }
    }
</style>
